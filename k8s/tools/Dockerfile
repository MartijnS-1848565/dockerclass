# The Kubernetes toolbox: docker CLI + k3d + kubectl in one image, so the host needs nothing but Docker.
# It controls the HOST's Docker through the socket mounted in compose.yaml, so the cluster
# containers it creates run next to the rest of the lab, not inside this container.
FROM docker:27.3-cli

ARG KUBECTL_VERSION=v1.31.5
ARG K3D_VERSION=v5.7.4
# Filled in automatically by the builder: amd64 on most PCs, arm64 on Apple Silicon.
ARG TARGETARCH

RUN apk add --no-cache curl \
    && curl -fsSL -o /usr/local/bin/kubectl "https://dl.k8s.io/release/${KUBECTL_VERSION}/bin/linux/${TARGETARCH}/kubectl" \
    && curl -fsSL -o /usr/local/bin/k3d "https://github.com/k3d-io/k3d/releases/download/${K3D_VERSION}/k3d-linux-${TARGETARCH}" \
    && chmod +x /usr/local/bin/kubectl /usr/local/bin/k3d

COPY lab-k8s.sh /usr/local/bin/lab-k8s
RUN chmod +x /usr/local/bin/lab-k8s

ENTRYPOINT ["lab-k8s"]
CMD ["help"]
