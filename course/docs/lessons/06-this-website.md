# Lesson 6: This website

The page you're reading is served by the `course` container. It's a good example of a **multi-stage build**.

```dockerfile title="course/Dockerfile"
--8<-- "course/Dockerfile"
```

- **Stage 1** (`build`) uses an image with Python and [MkDocs](https://squidfunk.github.io/mkdocs-material/) to turn the markdown in `course/docs/` into a folder of HTML.
- **Stage 2** starts again from a small nginx image and copies in **only** that folder.

Compare the sizes:

```bash
docker images | grep -E 'mkdocs-material|docker-laravel-api-lab-course'
```

The final image doesn't contain Python, MkDocs or the markdown sources, only the HTML. Smaller images download faster and contain less that could be attacked.

## Code that's always up to date

The code blocks in these lessons aren't copied by hand. The markdown contains lines like:

```text
;--8<-- "api/src/routes/api.php"
```

At build time MkDocs replaces such a line with the **current** contents of that file. Change the controller, rebuild the `course` image, and the docs show the new code. If a file is missing, `--strict` makes the build fail instead of publishing a broken page.

## The build context and `.dockerignore`

In `compose.yaml` the course is built with:

```yaml
  course:
    build:
      context: .                      # the whole lab folder is sent to the builder...
      dockerfile: course/Dockerfile   # ...but the Dockerfile lives in course/
```

The context is the whole lab because the docs embed files from `api/`, `client/` and `proxy/`. To keep the `.env` file with passwords **out** of the image, the lab has a `.dockerignore`:

```text title=".dockerignore"
--8<-- ".dockerignore"
```

Check it: `docker compose exec course ls -R /usr/share/nginx/html` shows only the built site (HTML, CSS, JS and images): no `.env` and no markdown sources.

## The look: KU Leuven house style

The site uses the Material theme for MkDocs, adjusted in two ways:

- **CSS**: `extra_css` in `mkdocs.yml` adds `course/docs/stylesheets/kuleuven.css`, which sets the KU Leuven colours. The fonts are set under `theme.font`.
- **Template overrides**: `custom_dir: overrides` tells Material to look in `course/overrides/` first. A file there **replaces** Material's template with the same path. This lab overrides two small ones: `partials/logo.html` (the logos in the header) and `partials/copyright.html` (the footer). The logo files are in `course/docs/assets/images/`.

```html title="course/overrides/partials/logo.html"
--8<-- "course/overrides/partials/logo.html"
```

Override as little as possible: every file you replace no longer gets fixes from new Material versions.

## Edit the course

1. Edit a page in `course/docs/`, or add one and list it under `nav:` in `course/mkdocs.yml`. (Colours are in `course/docs/stylesheets/`, header and footer templates in `course/overrides/`.)
2. Run `docker compose up -d --build course`.
3. Refresh <http://LAB_ADDR/course/>.

For a live preview that reloads on every save, run the MkDocs image directly from the lab folder:

```bash
docker run --rm -it -p 8000:8000 -v "$PWD:/lab" -w /lab \
  squidfunk/mkdocs-material:9.5.39 serve -f course/mkdocs.yml -a 0.0.0.0:8000
```

Then open <http://LAB_HOST:8000>.
