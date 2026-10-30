<?php
// "Who am I?" - a page that tells you which container and which node answered.
// The orchestrator hands these facts to the container as environment variables:
// Kubernetes in k8s/manifests/deployment.yaml, Swarm in swarm/stack.yaml.

$container = getenv('CONTAINER_NAME') ?: gethostname();
$node = getenv('NODE_NAME') ?: 'unknown (not running in Kubernetes)';
$ip = getenv('CONTAINER_IP') ?: ($_SERVER['SERVER_ADDR'] ?? '?');
$version = getenv('APP_VERSION') ?: 'dev';

// A request counter stored INSIDE this container. Every container has its own count:
// containers don't share files, so state like this is lost when one is replaced.
$counterFile = sys_get_temp_dir().'/requests';
$handle = fopen($counterFile, 'c+');
flock($handle, LOCK_EX);
$count = (int) stream_get_contents($handle) + 1;
ftruncate($handle, 0);
rewind($handle);
fwrite($handle, (string) $count);
flock($handle, LOCK_UN);
fclose($handle);

// One line of text for curl loops: curl -s "http://<host>:8090/?format=text"
if (($_GET['format'] ?? '') === 'text') {
    header('Content-Type: text/plain');
    echo "node={$node} container={$container} version={$version} requests={$count}\n";
    return;
}

// Same node = same colour, so you can see the spread at a glance.
$hue = crc32($node) % 360;
$refresh = isset($_GET['refresh']);
$h = fn (string $value) => htmlspecialchars($value, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($refresh): ?><meta http-equiv="refresh" content="1"><?php endif ?>
    <title>whoami: <?= $h($container) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center;
               background: hsl(<?= $hue ?> 70% 92%); color: #1d1d1f; }
        main { background: #fff; border-radius: 12px; padding: 2rem 2.5rem; box-shadow: 0 4px 20px rgb(0 0 0 / 0.08);
               border-top: 8px solid hsl(<?= $hue ?> 65% 45%); max-width: 36rem; width: calc(100% - 2rem); box-sizing: border-box; }
        h1 { margin: 0 0 1.5rem; font-size: 1.3rem; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.6rem 1.2rem; margin: 0; }
        dt { color: #666; }
        dd { margin: 0; font-family: ui-monospace, monospace; font-weight: 600; word-break: break-all; }
        p { margin: 1.5rem 0 0; color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>
    <main>
        <h1>This request was answered by…</h1>
        <dl>
            <dt>Node</dt><dd><?= $h($node) ?></dd>
            <dt>Container (k8s pod / swarm task)</dt><dd><?= $h($container) ?></dd>
            <dt>Container IP</dt><dd><?= $h($ip) ?></dd>
            <dt>Version</dt><dd><?= $h($version) ?></dd>
            <dt>Requests to this container</dt><dd><?= $count ?></dd>
        </dl>
        <p>
            Reload to be sent to another container.
            <?php if ($refresh): ?><a href="?">Stop auto-refresh</a><?php else: ?><a href="?refresh">Auto-refresh every second</a><?php endif ?>
        </p>
    </main>
</body>
</html>
