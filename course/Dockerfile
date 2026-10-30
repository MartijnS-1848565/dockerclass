# The course website: markdown in course/docs → static HTML → served by nginx.
#
# Multi-stage build:
#   stage 1 "build" has Python + MkDocs (~150 MB of tooling) and turns markdown into HTML.
#   stage 2 is a tiny nginx image that only receives the finished HTML.
# The tooling from stage 1 never ends up in the image you run.
#
# The build context is the WHOLE lab folder (see compose.yaml), because the docs
# embed the real source files (compose.yaml, controllers, ...) at build time.
FROM squidfunk/mkdocs-material:9.5.39 AS build

WORKDIR /lab
COPY . .
# --strict: fail the build on broken links or missing embedded files.
RUN mkdocs build --strict -f course/mkdocs.yml -d /site

FROM nginx:1.27-alpine

COPY --from=build /site /usr/share/nginx/html
COPY course/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
