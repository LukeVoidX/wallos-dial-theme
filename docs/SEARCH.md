# Search visibility and AI search / 搜索可见性

Wallos Dial's public product pages are static, bilingual GitHub Pages documents:

- English: `https://lukevoidx.github.io/wallos-dial-theme/`
- Simplified Chinese: `https://lukevoidx.github.io/wallos-dial-theme/zh-CN/`
- Sitemap: `https://lukevoidx.github.io/wallos-dial-theme/sitemap.xml`
- Real demo: `https://wallos-demo.mostai.org/` (read-only, fictional data, intentionally `noindex`)

The product pages contain visible descriptions of features, version compatibility, installation and limits. Each has a self-referencing canonical URL, reciprocal `hreflang` entries, a unique title and description, social preview tags, a theme favicon, and factual `SoftwareSourceCode`/`WebPage` JSON-LD. The schema identifies the repository and license; it makes no rating or review claim. The sitemap lists only the two pages intended for indexing.

Google states that its AI search features rely on the same foundational SEO as ordinary Search. Indexing and AI citation are not guaranteed by these files. We therefore avoid fabricated reviews, keyword stuffing, FAQ rich-result claims, and a claim that `llms.txt` is a ranking signal. Sources: [AI features](https://developers.google.com/search/docs/appearance/ai-features), [generative AI search guidance](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide), [localized versions](https://developers.google.com/search/docs/specialty/international/localized-versions), [sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

## Remaining external check

The pages and sitemap can be checked publicly without signing in. To see whether Google crawls or indexes them, the site owner must verify the appropriate GitHub Pages URL-prefix property in Google Search Console and submit the sitemap there. A sitemap submission is only a discovery hint, and indexing can take time or may not occur. This repository does not contain a Search Console verification token. A project site cannot place `robots.txt` at the `lukevoidx.github.io` host root, so a `docs/robots.txt` file would not control crawler rules for this project.

## 中文说明

英文和简体中文介绍页分别有独立地址、标题、正文与 canonical，并互相标注 `hreflang`。真实 Demo 使用虚构数据，刻意不收录；搜索入口由可阅读的 GitHub Pages 介绍页承担。页面提供真实功能、兼容版本、安装方式和限制，方便普通搜索与 AI 搜索引用可核实的内容。`sitemap.xml` 只列出希望被收录的两个介绍页；它不保证收录。若要查看实际索引状态，还需由站点所有者在 Google Search Console 验证地址并提交站点地图。
