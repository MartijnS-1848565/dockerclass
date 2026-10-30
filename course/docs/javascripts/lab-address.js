// The lab can run anywhere: your laptop, a VM, a server with a real name. So the pages don't
// hard-code an address. They write two placeholders instead, and this script swaps them for
// the address in your browser's address bar, the one you actually used to reach the lab:
//   LAB_ADDR → host and port of the lab's proxy (what you typed before /course/)
//   LAB_HOST → just the host, for ports published straight on the Docker host (8081, 8090, ...)
(function () {
  // Served by the lab's proxy under /course/: the proxy's address is this page's address.
  // Anywhere else (e.g. the live preview on port 8000), assume the proxy's default port 8080.
  var host = location.hostname;
  var addr = location.pathname.indexOf("/course/") === 0 ? location.host : host + ":8080";
  var pattern = /LAB_ADDR|LAB_HOST/g;
  function swap(text) {
    return text.replace(pattern, function (m) { return m === "LAB_ADDR" ? addr : host; });
  }

  function fill(root) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (var node; (node = walker.nextNode()); ) {
      if (pattern.test(node.nodeValue)) node.nodeValue = swap(node.nodeValue);
      pattern.lastIndex = 0;
    }
    if (root.querySelectorAll) {
      root.querySelectorAll("a[href*='LAB_']").forEach(function (a) {
        a.setAttribute("href", swap(a.getAttribute("href")));
      });
    }
  }

  fill(document.body);
  // Search results and other content Material adds later get the same treatment.
  new MutationObserver(function (changes) {
    changes.forEach(function (c) {
      c.addedNodes.forEach(function (n) {
        if (n.nodeType === Node.ELEMENT_NODE) fill(n);
        else if (n.nodeType === Node.TEXT_NODE && pattern.test(n.nodeValue)) n.nodeValue = swap(n.nodeValue);
        pattern.lastIndex = 0;
      });
    });
  }).observe(document.body, { childList: true, subtree: true });
})();
