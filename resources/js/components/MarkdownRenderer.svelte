<script>
  import { marked } from 'marked';
  import hljs from 'highlight.js';
  import 'highlight.js/styles/atom-one-dark.css';
  import '@styles/Markdown.scss';

  let { content } = $props();

  // Configure marked with syntax highlighting
  marked.setOptions({
    breaks: true,
    gfm: true,
    highlight: function(code, lang) {
      if (lang && hljs.getLanguage(lang)) {
        try {
          return hljs.highlight(code, { language: lang }).value;
        } catch (e) {
          console.error('Highlight error:', e);
        }
      }
      return hljs.highlightAuto(code).value;
    }
  });

  // Custom renderer for code blocks with copy button
  const renderer = new marked.Renderer();
  renderer.code = function(code, language) {
    const lang = language || 'plaintext';
    const highlighted = this.options.highlight(code, lang);
    return `
      <div class="code-block">
        <div class="code-header">
          <span class="language">${lang}</span>
          <button class="copy-button" onclick="navigator.clipboard.writeText(\`${code.replace(/`/g, '\\`')}\`).then(() => { this.textContent = '✓ Copied!'; setTimeout(() => this.textContent = 'Copy', 2000); })">Copy</button>
        </div>
        <pre><code class="hljs language-${lang}">${highlighted}</code></pre>
      </div>
    `;
  };

  marked.use({ renderer });

  let html = $derived.by(() => {
    if (!content) return '';
    return marked.parse(content);
  });
</script>

<div class="markdown-content">
  {@html html}
</div>
