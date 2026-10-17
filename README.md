# Template Builder & Document Generation Service

A visual drag-and-drop template builder with a document generation API. Users compose a
document from predefined blocks on a 12-column grid, save it as structured JSON, and export
it to PDF or Word (DOCX).

| Layer    | Technology                                   | Location    |
|----------|----------------------------------------------|-------------|
| Frontend | React 19, Vite 8 (HTML5 drag & drop)         | `frontend/` |
| Backend  | Laravel 13, PHP 8.4                          | `backend/`  |
| Database | PostgreSQL 17                                | —           |
| PDF      | Dompdf 3.1 (HTML → PDF)                      | backend     |
| DOCX     | PHPOffice PHPWord 1.4 (native Word document) | backend     |

---

## 1. Architecture

```
┌──────────────────────────── React (Vite, :5173) ────────────────────────────┐
│  TemplateList ──► Builder                                                   │
│                    ├─ ComponentPalette   (blocks from GET /api/components)  │
│                    ├─ Canvas             (sections, reorder, grid resize)   │
│                    ├─ PropertyPanel      (props, colStart / colSpan, photo) │
│                    └─ PreviewPane        (iframe ← POST /api/render/preview)│
│                         api.js  (fetch + timeouts, blob downloads)          │
└───────────────────────────────────┬─────────────────────────────────────────┘
                                    │ JSON over HTTP
┌───────────────────────────────────▼────────── Laravel API (:8000) ──────────┐
│  routes/api.php                                                             │
│   ├─ ComponentController  → ComponentCatalog                                │
│   ├─ TemplateController   → Template (Eloquent) → PostgreSQL `templates`    │
│   ├─ UploadController     → storage/app/public/uploads                      │
│   └─ RenderController     → DocumentRenderer                                │
│                                ├─ HtmlRenderer  (preview + PDF markup)      │
│                                ├─ PdfRenderer   (Dompdf)                    │
│                                └─ DocxRenderer  (PHPWord)                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Key principle:** the JSON `structure` is the single source of truth. HTML, PDF and DOCX are
all derived from it at request time; no rendered output is stored.

---

## 2. Data model

### Table `templates`

| Column        | Type        | Notes                              |
|---------------|-------------|------------------------------------|
| `id`          | bigint, PK  |                                    |
| `name`        | varchar     | required                           |
| `description` | varchar     | nullable                           |
| `structure`   | json        | layout definition (see below)      |
| `settings`    | json        | nullable, reserved for page options|
| `created_at`, `updated_at` | timestamps |                         |

Migration: `backend/database/migrations/2026_10_16_173322_create_templates_table.php`

### Template structure (JSON)

```json
{
  "version": 1,
  "sections": [
    {
      "id": "0d8a…",
      "type": "header",
      "grid": { "colStart": 1, "colSpan": 12 },
      "props": { "title": "Company Proposal", "subtitle": "Q4 2026", "align": "left" }
    },
    {
      "id": "7f21…",
      "type": "image",
      "grid": { "colStart": 5, "colSpan": 4 },
      "props": { "src": "http://localhost:8000/storage/uploads/photo.jpg", "alt": "Photo", "caption": "", "height": 282 }
    }
  ]
}
```

- **Order** of `sections` is the document order.
- **Placement** uses a 12-column grid: `colStart` (1–12) and `colSpan` (1–12). There are no
  absolute X/Y coordinates.
- `props` depends on `type` (see the component catalog).

### Component catalog

Defined once in `backend/app/Support/ComponentCatalog.php`; used both by
`GET /api/components` and by request validation (`Rule::in(ComponentCatalog::types())`).

| Type         | Props                                   |
|--------------|-----------------------------------------|
| `header`     | `title`, `subtitle`, `align`            |
| `text`       | `content`, `align`                      |
| `image`      | `src`, `alt`, `caption`, `height` (px)  |
| `two_column` | `left`, `right`                         |
| `footer`     | `text`, `showPageNumber`                |

---

## 3. API reference

Base URL: `http://127.0.0.1:8000/api`. All bodies are JSON unless noted.

| Method | Endpoint               | Description                         | Response               |
|--------|------------------------|-------------------------------------|------------------------|
| GET    | `/components`          | Available builder blocks            | `{ data: [...] }`      |
| GET    | `/templates`           | List templates (latest first)       | `{ data: [...] }`      |
| GET    | `/templates/{id}`      | Fetch one template                  | `{ data: {...} }`      |
| POST   | `/templates`           | Create template                     | `201`, `{ data }`      |
| PUT    | `/templates/{id}`      | Update template                     | `{ data }`             |
| DELETE | `/templates/{id}`      | Delete template                     | `204`                  |
| POST   | `/uploads`             | Upload image (`multipart/form-data`, field `image`) | `201`, `{ data: { url, path, name } }` |
| POST   | `/render/preview`      | Render HTML preview                 | `text/html`            |
| POST   | `/render/pdf`          | Generate PDF                        | file download          |
| POST   | `/render/docx`         | Generate DOCX                       | file download          |

### Validation

- **Create / update** (`StoreTemplateRequest`, `UpdateTemplateRequest`): `name` required;
  `structure.sections` non-empty array; each section requires `id`, a known `type`, and
  `grid.colStart` / `grid.colSpan` integers between 1 and 12.
- **Render** (`RenderDocumentRequest`): send either `template_id` or `structure`.
  With `template_id`, an optional `structure` / `name` overrides the stored values, which lets
  the builder preview or export unsaved changes.
- **Upload** (`UploadImageRequest`): jpeg, jpg, png, gif or webp, max 5 MB.

### Examples

```bash
# Render an unsaved structure to PDF
curl -X POST http://127.0.0.1:8000/api/render/pdf \
  -H "Content-Type: application/json" \
  -d '{"name":"Demo","structure":{"version":1,"sections":[{"id":"a","type":"header","grid":{"colStart":1,"colSpan":12},"props":{"title":"Hello"}}]}}' \
  -o demo.pdf

# Export a saved template to Word
curl -X POST http://127.0.0.1:8000/api/render/docx \
  -H "Content-Type: application/json" -d '{"template_id":1}' -o template.docx
```

---

## 4. Rendering pipeline

`RenderController` → `DocumentRenderer` (`backend/app/Services/Document/`).

### HTML (preview) and PDF

`HtmlRenderer` turns each section into markup; `PdfRenderer` passes the same markup to Dompdf.
The preview iframe and the PDF therefore use identical HTML and CSS.

- **Grid placement:** Dompdf does not support CSS Grid or Flexbox, so each section is wrapped
  in a three-cell table (`grid-row`): left spacer, content, right spacer, with percentage widths
  derived from `colStart` / `colSpan`. Example: `colStart 5, colSpan 4` → 33.33% / 33.33% / 33.33%.
- **Images:** files under `/storage/...` are read from disk and embedded as base64 data URIs.
  Dompdf runs with `isRemoteEnabled = false`. This avoids a deadlock where Dompdf would request
  an image from the same single-threaded `php artisan serve` process that is generating the PDF.
- **Page:** A4 portrait, DejaVu Sans (Unicode-safe), chroot set to the backend base path.

### DOCX

`DocxRenderer` builds the Word document natively with PHPWord from the JSON (not HTML → Word),
which produces real Word headings, paragraphs, tables and images.

- A4, 1.5 cm margins, Arial; sections flow continuously (no forced page breaks).
- Header: 26 pt bold title, italic subtitle, two-colour accent line.
- Image: centred, fixed width, height from `props.height` (clamped 80–600 px).
- Two-column: borderless 3-cell table; footer: top-bordered table with text and page label.
- Image sources: local `/storage` files, data URIs, and `https://` URLs.

---

## 5. Frontend

`frontend/src/`

| File                          | Responsibility                                                   |
|-------------------------------|------------------------------------------------------------------|
| `api.js`                      | Fetch wrapper (10 s timeout, 60 s for exports), blob downloads   |
| `App.jsx`                     | Switches between library and builder; error banner with retry    |
| `components/TemplateList.jsx` | Template library, create / open / delete                         |
| `components/Builder.jsx`      | Editor state (`structure`, selection, edit/preview mode), save, export |
| `components/ComponentPalette.jsx` | Draggable / clickable block list                             |
| `components/Canvas.jsx`       | Section cards; drop target for blocks, photos and reordering     |
| `components/ResizeHandles.jsx`| Left/right handles that snap `colStart` / `colSpan` to 12 columns |
| `components/ImageDropZone.jsx`| Photo drag & drop, replace, bottom handle for image height       |
| `components/PropertyPanel.jsx`| Inspector for props and grid values                              |
| `components/PreviewPane.jsx`  | Iframe showing `POST /render/preview` output                     |
| `utils/blocks.js`             | `createBlock`, `emptyStructure`, `moveItem`                      |
| `index.css`                   | Styles (DM Sans / Syne, teal palette)                            |

### Interaction model

- **Add:** click a block in the palette, or drag it onto the canvas.
- **Reorder:** drag a section card onto another position.
- **Resize width:** select a section and drag its left/right edge. The pointer position is
  converted to a column index (`ceil(x / columnWidth)`) and written to `grid`.
- **Resize photo height:** drag the bottom handle of an image (80–600 px), stored in `props.height`.
- **Photos:** drop a file onto an image section (uploaded via `POST /uploads`), or onto empty
  canvas space to create a new image section.
- **Preview:** the "Preview document" tab renders the current, possibly unsaved, structure.

---

## 6. Setup

### Prerequisites

- PHP 8.3+ with extensions `openssl`, `pdo_pgsql`, `mbstring`, `curl`, `zip`, `gd`, `fileinfo`
- Composer 2
- Node.js 18+
- PostgreSQL 17

### Database

```bash
psql -U postgres -h 127.0.0.1 -p 5433 -c "CREATE DATABASE template_builder;"
```

On the development machine PostgreSQL 17 listens on **5433**, because 5432 is taken by another
PostgreSQL install. Use 5432 if that is where your server runs.

### Backend

```bash
cd backend
composer install
copy .env.example .env        # Windows (cp on macOS/Linux)
php artisan key:generate
php artisan migrate --seed    # creates `templates` and a sample "Company Proposal"
php artisan storage:link      # exposes uploaded photos under /storage
php artisan serve --host=127.0.0.1 --port=8000
```

Relevant `.env` values:

```
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=template_builder
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

### Frontend

```bash
cd frontend
npm install
npm run dev -- --host 127.0.0.1 --port 5173
```

`frontend/.env`:

```
VITE_API_URL=http://127.0.0.1:8000/api
```

Open `http://127.0.0.1:5173`.

---

## 7. Tests and code style

```bash
cd backend
php artisan test --compact    # feature tests: components, CRUD, preview, PDF, DOCX, uploads
vendor/bin/pint               # PHP code style
```

Tests live in `backend/tests/Feature/` (`TemplateApiTest.php`, `UploadImageTest.php`).

---

## 8. Extending

Adding a new block type (for example `table`):

1. Add its definition (type, label, `defaultGrid`, `defaultProps`) to `ComponentCatalog::all()`.
   Validation and `GET /components` pick it up automatically.
2. Add a `renderTable()` branch in `HtmlRenderer` (preview + PDF) and in `DocxRenderer`.
3. Add its canvas preview in `Canvas.jsx` and its fields in `PropertyPanel.jsx`.

Adding a new output format: implement a renderer that accepts the structure and add a case to
`DocumentRenderer::export()` plus a route in `routes/api.php`.

---

## 9. Known limitations

- No authentication or multi-user ownership (out of scope for the MVP).
- DOCX is structurally equivalent to the PDF but not pixel-identical (Word has its own layout
  engine). Sections are rendered full width in DOCX; `colStart` / `colSpan` only affect the
  preview and PDF.
- Each section occupies its own row; two sections cannot share a row side by side.
- `php artisan serve` is single-threaded; use PHP-FPM / a real web server in production.
- CORS currently allows all origins (`config/cors.php`); restrict it for deployment.
