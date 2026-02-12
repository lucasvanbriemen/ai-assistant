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
  marked.use({ renderer });

  let html = $derived.by(() => {
    if (!content) return '';
    return marked.parse(content);
  });
</script>

<div class="markdown-content">
  {@html html}
</div>
