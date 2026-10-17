# Wallos Dial

![Wallos Dial 主视觉：每一次续订，都心中有数](docs/assets/posters/campaign-hero-zh.png)

[中文介绍页](https://lukevoidx.github.io/wallos-dial-theme/zh-CN/) · [立即体验在线 Demo](https://wallos-demo.mostai.org/) · [English](README.md) · [分类管理](docs/CATEGORIES.zh-CN.md) · [图标选择](docs/zh-CN/ICONS.md) · [中文海报](docs/CAMPAIGN.zh-CN.md) · [改版前后与原始截图](docs/SHOWCASE.md) · [安装、升级和回退](docs/zh-CN/UPGRADING.md)

Wallos Dial 是为 [Wallos](https://github.com/ellite/Wallos) 设计的桌面界面改版。暖白底、黑色数字、细线和少量红色贯穿总览、订阅、日历与统计页面。首页集中显示月度费用、日期刻度、近期付款和倒计时；订阅页增加分类区块。原有搜索、筛选、排序、新增与编辑、通知和设置功能继续由 Wallos 处理。

**Dial 1.0.0 目标版本：**仅适配 Wallos 5.8.3。当前源码是已在本地测试的 1.0.0 发布候选版，Git 标签与镜像尚未发布；已公开的镜像和 Demo 仍是基于 Wallos 5.8.1 的 0.1.1，直至 1.0 审核、发布和部署完成。GitHub 与 GHCR 出现正式版本前，请勿尝试拉取 `1.0.0` 镜像。Dial 是固定 Wallos 镜像上的源码叠加层，并非应用内置的可切换主题，也不隶属于 Wallos 官方。

**先体验，再安装：**[打开真实运行的中英文 Demo](https://wallos-demo.mostai.org/)。它在独立容器运行实际 PHP 应用，并放入 30 条虚构订阅。可浏览总览、分类订阅、日历、统计、详情与语言切换。公开环境只读；Demo 会在 1.0 发布获批后另行更新，进入时请先核对显示的版本。[了解真实 Demo 的部署与边界](docs/DEMO.md)。

![Wallos Dial 总览展示：内嵌真实运行界面的截图](docs/assets/posters/showcase-overview.png)

这张海报中的界面来自早期版本的隔离示例账号，名称、金额和标识均为虚构。ImageGen 只用于海报背景，内嵌界面保持真实浏览器截图。[查看 1.0 本地候选版的直接截图](docs/SHOWCASE.md)：首页、分类订阅、分类管理、图标选择器及统计页面使用同一组 30 条虚构订阅。公开 Demo 目前仍为 0.1.1；这些候选版截图不代表已公开部署。

![Wallos Dial 分类订阅展示](docs/assets/posters/campaign-ledger-zh.png)

![Wallos Dial 日期刻度与倒计时，使用真实界面截图](docs/assets/posters/campaign-time-zh.png)

[查看完整中文海报系列](docs/CAMPAIGN.zh-CN.md)，包括日历、统计与真实在线 Demo；也可切换到[英文版](docs/CAMPAIGN.md)。

![设计思路：清晰层级、时间刻度、分类与克制的红色](docs/assets/posters/design-principles.png)

![同一组虚构订阅在原版纯文本提醒与 Dial 邮件模板中的样式对比](docs/assets/posters/compare-email.png)

邮件对比图由原版 Wallos 5.8.1 的纯文本提醒正文和 Dial 0.1.1 实际生成的 HTML 模板渲染而成，不是邮箱客户端截图。两侧使用同一组虚构数据。[查看制作方法](docs/SHOWCASE.md)。

## 新安装

需要 Docker、Compose，以及本机空闲的 `8282` 端口：

**1.0 尚未发布时：**请只在隔离实例使用 `docker compose -f compose.build.yaml up -d --build` 构建本地候选版。以下直接拉取镜像的命令适用于 `compose.yaml` 和 GHCR 都升至正式 `1.0.0` 之后。

```bash
git clone https://github.com/LukeVoidX/wallos-dial-theme.git
cd wallos-dial-theme
docker compose up -d
docker compose ps
```

在运行 Docker 的机器上打开 `http://127.0.0.1:8282`，按 Wallos 原有流程注册。默认只监听 `127.0.0.1`；如果 Docker 在远程服务器，可通过 SSH 转发访问：

```bash
ssh -L 8282:127.0.0.1:8282 user@your-server
```

随后在自己的电脑打开 `http://127.0.0.1:8282`。需要公开访问时，请自行配置 HTTPS 反向代理和访问控制。数据库和上传的 Logo 分别保存在 `./data/db` 与 `./data/logos`，不会提交进 Git。时区可在本地 `.env` 中设置 `TZ=Asia/Taipei` 等有效值。

用 `curl -fsS http://127.0.0.1:8282/health.php` 检查服务。遇到端口、权限、Logo 或缓存问题，先看[故障排查](docs/TROUBLESHOOTING.md)。

若使用 Colima 或远程 Docker，首次注册前还要确认主机上确实出现 `./data/db/wallos.db`；未出现时先停止容器，核对目录共享与挂载位置。容器内部的数据库不能代替主机备份。

## 使用现有 Wallos 数据

1. 先用 SQLite 的 `.backup` 备份现有 `wallos.db`，并单独备份上传的 Logo、当前 Compose 文件和镜像版本。
2. 在仓库目录建立不提交到 Git 的 `.env`，写入现有目录的**绝对路径**：

   ```dotenv
   WALLOS_DB_DIR=/absolute/path/to/current/db
   WALLOS_LOGOS_DIR=/absolute/path/to/current/logos
   TZ=Asia/Taipei
   ```

3. 运行 `docker compose config`，核对解析出的挂载目录。停止旧 Wallos 容器后，再运行 `docker compose up -d`；不要让两个容器同时写同一数据库。
4. 登录后核对订阅数量、Logo、总览、日历、统计、设置和通知。无需重新录入订阅。

如果原部署还有额外环境变量、代理或自定义端口，应按实际情况带入新部署。[中文升级与回退说明](docs/zh-CN/UPGRADING.md)包含版本切换、备份与还原步骤。

分类可以在订阅页或设置页打开独立的管理界面，支持新增、改名、排序、合并和删除空分类。初始分类为可选操作，不会覆盖已有订阅。[查看分类管理说明](docs/CATEGORIES.zh-CN.md)。

## 图标与语言

已有上传 Logo 会保留。新增／编辑表单的「图标」区域，以及卡片上的「更换图标」操作，可选择原图、九枚主题图标或「我的图标」，并设置有无边框、彩色／单色及 80%–120% 显示大小。主题图标是统一绘制的中性原创符号，使用服务名称作识别，不包含第三方品牌 Logo；[来源及设计规则](theme/images/dial-catalog/SOURCES.md)已列明。「原图」会恢复 Wallos 原有 Logo。「我的图标」按账户保存在 SQLite 数据库，升级前除了 Logo 数据卷，也必须备份数据库。[查看图标操作及上传限制](docs/zh-CN/ICONS.md)。

已有的私人 SVG 文件映射仍可使用：将 SVG 放入 `config/icons/`，从 [`config/logo-map.example.php`](config/logo-map.example.php) 复制 `config/logo-map.local.php`，建立上传文件名到 SVG 文件名的映射，再启用 `compose.yaml` 的只读挂载。它只在没有明确图标选择时生效；选择「原图」可覆盖旧映射。私人文件、上传的 Logo 和映射不进入公开包。

顶栏提供简体中文、English、繁體中文的快捷选项，也保留 Wallos 其他语言。选择会保存到现有账户语言字段和 Cookie；用户自己录入的订阅名称不会自动翻译。

## 邮件通知

Dial 1.0 沿用 0.1.1 引入的邮件设计，将续费、取消、SMTP 测试、邮箱验证和密码重置邮件统一为暖白 HTML 模板，并附带纯文本版本。收件人、发送时间和 SMTP 配置仍沿用 Wallos 原有规则；续费、取消及可选的期初摘要纯文本会按收件人账户语言显示英文、简体中文或繁体中文。续费、取消、邮箱验证与密码重置的邮件主题行也按该语言显示。其他语言回退英文，用户录入的订阅名称和金额保持原样。

提醒邮件中的“打开 Wallos”按钮读取管理设置里的 **Server URL**（或部署管理的 `WALLOS_SERVER_URL`）；未设置或无效时不显示按钮。验证与重置密码邮件必须有有效的 HTTP(S) Server URL，否则保留待发送状态，避免发出无法使用的链接。安装后请按原有方式设置 SMTP，并先发送测试邮件。

## 更新边界

Dial `1.0.0` 的目标基底固定为 Wallos `5.8.3`；目前仍在本地候选阶段。Wallos 发布新版本后，本主题**不会自动跳到未经验证的上游版本**。维护者需检查被替换文件的差异，更新适配，测试数据副本，然后发布新的 Dial 镜像。现有版本可以继续运行；不要直接把 Dockerfile 的 Wallos 版本号改新就上线。详见[升级和回退](docs/zh-CN/UPGRADING.md)。

此版本重点是桌面体验，没有单独承诺手机重新设计，也不承诺未来每次 Wallos 更新都零故障。

## 项目与许可

- [英文完整说明](README.md)
- [改版展示与原始截图](docs/SHOWCASE.md)
- [故障排查](docs/TROUBLESHOOTING.md)
- [贡献指南](CONTRIBUTING.md)

Wallos Dial 基于 Wallos 改作，沿用 [GPLv3](LICENSE.md)，保留上游归属；不代表 Wallos 官方认可。
