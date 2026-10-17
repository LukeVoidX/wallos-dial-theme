# 升级与回退

[简体中文](UPGRADING.md) · [English](../UPGRADING.md)

Wallos Dial 1.0.0 是针对 **Wallos 5.8.3** 的[固定版本正式发布](https://github.com/LukeVoidX/wallos-dial-theme/releases/tag/v1.0.0)，基底经过摘要校验。本文说明从 **Dial 0.1.1 / Wallos 5.8.1** 升到 1.0.0 的步骤。操作正式实例前，先让已发布的 1.0.0 镜像在隔离数据副本上完成排练。旧版 0.1.1 镜像留作回退。

Dial 通过 Docker 镜像叠加源码，并非 Wallos 内置的主题开关。新版启动时，Wallos 和 Dial 都会运行数据库迁移；上游 Wallos 更新不会自动获得 Dial 兼容性。

## 1. 确认两个持久化目录

仓库的 [`compose.yaml`](../../compose.yaml) 把 `${WALLOS_DB_DIR:-./data/db}` 挂载到 `/var/www/html/db`，把 `${WALLOS_LOGOS_DIR:-./data/logos}` 挂载到 `/var/www/html/images/uploads/logos`。先核对**实际部署**的挂载：

```bash
docker compose config
docker inspect wallos-dial --format '{{range .Mounts}}{{println .Source "→" .Destination}}{{end}}'
```

旧容器可能使用其他名称、端口或 Compose 项目。记录准确的镜像版本、环境变量、代理配置、数据库与 Logo 目录，以及私人 SVG 挂载和映射（如有）。不要直接套用示例路径。「我的图标」保存在 SQLite 数据库，Wallos 原有上传 Logo 及可选的旧版私人 SVG 保存在独立目录；升级前必须同时备份数据库和 Logo 目录，私人映射也应单独保存。

## 2. 停止旧容器并备份

1. 确认旧实例正常，记录订阅数量、分类归属、语言、可见 Logo、通知设置和当前镜像版本。
2. 停止旧容器，等待 SQLite 连接关闭。整个切换期间，不要让两个 Wallos 容器同时写同一数据库。若服务名称不是 `wallos`，按实际 Compose 配置替换下面的名称。

   ```bash
   docker compose stop wallos
   ```

3. 在数据库和 Logo 挂载目录**之外**准备备份目录。以下三个路径都要换成当前服务器上的绝对路径；主机需安装 `sqlite3` 命令。在单独的 Bash shell 执行下方命令，任何一步失败就停止升级。

   ```bash
   set -euo pipefail
   DIAL_DB_DIR=/absolute/path/to/current/db
   DIAL_LOGOS_DIR=/absolute/path/to/current/logos
   DIAL_BACKUP_DIR=/absolute/path/to/backup-before-dial-1.0
   mkdir -p "$DIAL_BACKUP_DIR"
   test "$(sqlite3 "$DIAL_DB_DIR/wallos.db" 'PRAGMA integrity_check;')" = ok
   sqlite3 "$DIAL_DB_DIR/wallos.db" ".backup '$DIAL_BACKUP_DIR/wallos.db'"
   tar -C "$DIAL_LOGOS_DIR" -cf "$DIAL_BACKUP_DIR/logos.tar" .
   test "$(sqlite3 "$DIAL_BACKUP_DIR/wallos.db" 'PRAGMA integrity_check;')" = ok
   ```

保存旧 Compose 文件、镜像引用及私人 SVG 映射。不要把备份放在会随容器重建而清空的目录里。

## 3. 先在数据副本上排练

1. 从 `wallos.db` 与 `logos.tar` 分别恢复**副本**到隔离目录，让 1.0.0 镜像只挂载这些副本。核对 `docker compose config`，不要指向在线卷。
2. 检查登录、分类管理、原有 Logo、图标选择与「我的图标」上传、新增／编辑／克隆／删除订阅、总览、日历、统计和设置。邮件通知应通过本地 SMTP 捕获服务检查续费、取消、测试、验证和密码重置五种路径的 HTML 与纯文本内容；不要向真实收件人发送测试邮件。
   如果旧订阅的付款人 ID 指向其他账户，Dial 会跳过并记录该条取消提醒。排练时检查日志，并在订阅编辑器中改选本账户付款人，再依赖这条提醒。
3. 停止测试容器，再用**另一份升级前备份**启动 0.1.1，确认旧版仍显示原有 Logo，且可以对一条虚构订阅执行新增、编辑和删除。这是回退排练，不能直接在正式卷上进行。

## 4. 单容器切换

排练通过后，拉取已发布的 `ghcr.io/lukevoidx/wallos-dial:1.0.0`，或使用已审查的本地构建。只修改打算变更的 Dial 镜像引用和部署设置；再次检查 `docker compose config` 中的数据库、Logo 绝对路径，随后启动新容器。旧容器必须保持停止。

首次启动会先完成 Wallos 数据库与 Dial 迁移，并等待 PHP-FPM 监听就绪，随后才开放 Nginx；`/health.php` 只有在 Dial 迁移已记录时才返回 `OK`。请等该响应出现再测试登录或注册。若一直未就绪，先查看容器日志，并保留已停止的旧容器与配套备份以供回退。

启动后核对镜像版本、`/health.php`、登录、订阅数量、分类、原有 Logo、「我的图标」、总览、搜索／筛选／排序、日历、统计、设置、语言及通知配置。容器健康不代表数据和操作都正常。可以在容器停止后再次运行 SQLite `PRAGMA integrity_check`，或使用安全的只读连接。任何关键检查失败，就按下一节回退。

## 5. 回退到 0.1.1

1. 停止 1.0 容器。再次填写并核对 `DIAL_DB_DIR`、`DIAL_LOGOS_DIR`、`DIAL_BACKUP_DIR` 指向同一组**绝对路径**。选择一个在两个挂载目录之外、尚未存放 `db` 或 `logos` 子目录的失败状态保存位置，例如 `/absolute/path/to/failed-dial-1.0`。
2. 核实升级前备份完整。下方命令会把**当前失败状态**的两个目录移到保存位置，再重建原挂载路径，并从同一时间点的数据库与 Logo 备份恢复。它不会把旧的 `wallos.db-wal` 或 `wallos.db-shm` 混入还原后的数据库。

   ```bash
   set -euo pipefail
   DIAL_DB_DIR=/absolute/path/to/current/db
   DIAL_LOGOS_DIR=/absolute/path/to/current/logos
   DIAL_BACKUP_DIR=/absolute/path/to/backup-before-dial-1.0
   DIAL_FAILED_DIR=/absolute/path/to/failed-dial-1.0
   test -f "$DIAL_BACKUP_DIR/wallos.db"
   test -f "$DIAL_BACKUP_DIR/logos.tar"
   test "$(sqlite3 "$DIAL_BACKUP_DIR/wallos.db" 'PRAGMA integrity_check;')" = ok
   mkdir -p "$DIAL_FAILED_DIR"
   test ! -e "$DIAL_FAILED_DIR/db"
   test ! -e "$DIAL_FAILED_DIR/logos"
   mv "$DIAL_DB_DIR" "$DIAL_FAILED_DIR/db"
   mv "$DIAL_LOGOS_DIR" "$DIAL_FAILED_DIR/logos"
   mkdir -p "$DIAL_DB_DIR" "$DIAL_LOGOS_DIR"
   cp "$DIAL_BACKUP_DIR/wallos.db" "$DIAL_DB_DIR/wallos.db"
   tar -C "$DIAL_LOGOS_DIR" -xf "$DIAL_BACKUP_DIR/logos.tar"
   test "$(sqlite3 "$DIAL_DB_DIR/wallos.db" 'PRAGMA integrity_check;')" = ok
   ```

   如果任一命令失败，先检查路径和保存位置，不要启动容器。保留 `DIAL_FAILED_DIR` 供排查；不要覆盖升级前备份。若私人 SVG 映射或自定义配置在升级时改变，也从对应的旧备份恢复。

3. 恢复先前保存的 Compose 配置与镜像引用 `ghcr.io/lukevoidx/wallos-dial:0.1.1`。再次运行 `docker compose config` 核对挂载，然后只启动这个旧容器。核对健康状态、登录、订阅数量、原有 Logo 和正常的订阅操作。

不要把 0.1.1 直接连到状态未知的 1.0 迁移后数据库作为常规回退方案。旧版会忽略新增的 Dial 图标表，并显示原有 Logo；可靠恢复依赖同一时间点的数据库与 Logo 备份。升级后才新增的「我的图标」选择不在升级前备份里，需要时从保留的 1.0 失败状态中另行提取，不能随意和旧 Logo 快照混用。

## 维护者的后续兼容流程

上游版本检查只报告新版 Wallos，不自动批准升级。维护者需在分支上更新 `WALLOS_VERSION` 与镜像摘要，逐一审查 `compat/upstream-files.sha256` 覆盖的文件差异，并在新旧虚构数据上运行安全、分类与图标归属、五种邮件、Service Worker、布局和 0.1.1 回退测试。两种架构通过验证后，才发布新的固定版本镜像并更新中英文说明。未经检查的上游版本没有兼容保证。
