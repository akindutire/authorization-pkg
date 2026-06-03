# Documentation Website

This directory contains the GitHub Pages documentation website for the Akindutire Authorization package.

## Structure

```
docs/
├── index.html              # Landing page
├── installation.html       # Installation guide
├── quickstart.html        # Quick start tutorial
├── api.html               # API reference
├── configuration.html     # Configuration options
├── best-practices.html    # Best practices guide
├── css/
│   └── styles.css         # Main stylesheet
├── js/
│   └── main.js           # JavaScript for interactive features
└── images/               # Image assets (placeholder)
```

## Local Development

To preview the documentation locally, you can use any static file server:

### Using Python:
```bash
cd docs
python3 -m http.server 8000
# Visit http://localhost:8000
```

### Using PHP:
```bash
cd docs
php -S localhost:8000
# Visit http://localhost:8000
```

### Using Node.js (http-server):
```bash
npm install -g http-server
cd docs
http-server
# Visit http://localhost:8080
```

## Publishing to GitHub Pages

### Option 1: Using the docs/ folder (Recommended)

1. Push the docs folder to your repository:
   ```bash
   git add docs/
   git commit -m "Add documentation website"
   git push origin main
   ```

2. Enable GitHub Pages:
   - Go to your repository on GitHub
   - Click **Settings** → **Pages**
   - Under "Source", select **Deploy from a branch**
   - Select branch: **main**
   - Select folder: **/docs**
   - Click **Save**

3. Your site will be available at:
   ```
   https://[username].github.io/[repository-name]/
   ```

### Option 2: Using GitHub Actions

Create `.github/workflows/deploy-docs.yml`:

```yaml
name: Deploy Documentation

on:
  push:
    branches: [main]
    paths:
      - 'docs/**'

permissions:
  contents: read
  pages: write
  id-token: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/configure-pages@v3
      - uses: actions/upload-pages-artifact@v2
        with:
          path: 'docs'
      - uses: actions/deploy-pages@v2
```

## Custom Domain (Optional)

To use a custom domain:

1. Create a `CNAME` file in the `docs/` directory:
   ```
   docs.yourpackage.com
   ```

2. Configure DNS records:
   - Add a CNAME record pointing to `[username].github.io`
   - Or add A records pointing to GitHub Pages IPs

3. Enable "Enforce HTTPS" in GitHub Pages settings

## Features

- **Responsive Design**: Works on all device sizes
- **Syntax Highlighting**: Code examples with proper formatting
- **Interactive Tabs**: Switch between different code examples
- **Copy to Clipboard**: One-click code copying
- **Smooth Scrolling**: Navigate sections smoothly
- **Modern UI**: Clean, professional design

## Customization

### Colors

Update CSS variables in `css/styles.css`:

```css
:root {
    --primary-color: #3b82f6;
    --secondary-color: #8b5cf6;
    --dark-bg: #1e293b;
    /* etc. */
}
```

### Content

All HTML files can be edited directly. The structure is straightforward:

- Navigation is consistent across all pages
- Each page has a `.doc-content` section
- Footer is standardized

### Adding New Pages

1. Create a new HTML file (e.g., `advanced.html`)
2. Copy the structure from an existing page
3. Update navigation links in all pages
4. Add content to the `.doc-content` section

## Analytics (Optional)

To add Google Analytics:

Add before the closing `</head>` tag in all HTML files:

```html
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'GA_MEASUREMENT_ID');
</script>
```

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers

## License

MIT License - Same as the main package
