# Lesson 1: the smallest useful Dockerfile.
#
# FROM  - start from an existing image (here: nginx on Alpine Linux)
# COPY  - add files from your machine (the "build context") into the image
# EXPOSE - documents which port the process listens on (does NOT publish it)
#
# Build:  docker build -t hello-docker .
# Run:    docker run --rm -p 8081:80 hello-docker
# Open:   http://<host>:8081   (<host>: the machine running Docker)
FROM nginx:1.27-alpine

COPY index.html /usr/share/nginx/html/index.html

EXPOSE 80
