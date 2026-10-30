# Lesson 1: One container

Before Compose and Laravel, learn the three basic moves: **build** an image, **run** a container, **publish** a port.

## The Dockerfile

```dockerfile title="lesson-01-hello/Dockerfile"
--8<-- "lesson-01-hello/Dockerfile"
```

- An **image** is a read-only template: a filesystem plus a command to start.
- A **container** is a running instance of an image. You can start many containers from one image.

## Build and run

```bash
cd lesson-01-hello
docker build -t hello-docker .            # Dockerfile → image called "hello-docker"
docker images                             # your image is listed
docker run --rm -p 8081:80 hello-docker   # image → running container
```

Open <http://LAB_HOST:8081>. Press `Ctrl+C` to stop the container. `--rm` deletes it once it stops.

!!! info "`-p 8081:80` means host:container"
    Port **8081 on your machine** is forwarded to port **80 inside the container**, where nginx listens.
    Without `-p`, the container runs but you can't reach it from your machine.

## Try it

1. **Copied, not linked.** Change `index.html` and refresh the page. Nothing changes: the file was copied into the image at build time. Rebuild the image and run it again to see your change.
2. **Bind mount.** Mount the file from your machine instead. Now edits show up immediately:
   ```bash
   docker run --rm -p 8081:80 \
     -v "$PWD/index.html:/usr/share/nginx/html/index.html:ro" hello-docker
   ```
3. **Background containers.** Start it detached and with a name:
   ```bash
   docker run -d --name hello -p 8081:80 hello-docker
   docker ps                 # running containers
   docker logs hello         # nginx's access log
   docker exec -it hello sh  # a shell inside the container; look around, then type exit
   docker stop hello         # stop it
   docker rm hello           # delete it (or use --rm next time)
   ```
4. **Other ports.** Run it with `-p 9000:80`. Which URL works now?

??? question "Check yourself: what's the difference between `COPY` and `-v`?"
    `COPY` puts the file **inside the image** at build time. Every container from that image has it, but changing the file means rebuilding.
    `-v` **mounts** a file or folder from your machine into one running container. Changes show up immediately, but only on your machine. The image doesn't contain them.
