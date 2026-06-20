+++
draft = false
title = 'XMPP'
weight = 2
registration-needed = true
+++

<div id="xmpp-loggedin" style="display:none">
  <p><strong>JID:</strong> <span id="xmpp-jid"></span></p>
  <p><strong>Password:</strong> Use the same password that you used during registration.</p>
</div>
<div id="xmpp-loggedout" style="display:none">
  <p>XMPP has been around since 1999, originally called Jabber. It was built as an open and decentralized protocol so that anyone could run their own server and still talk to people on other servers. It works in the same way email works across providers.</p>
  <p>No ads reading your conversations, no algorithm deciding who sees what, no single corporation owning the network or able to shut it off. Just an open standard that's outlasted most of the modern chat apps that came after it.</p>
  <p>Please <a href="/signup/">sign up</a> to have an XMPP account.</p>
</div>
<script>
(function () {
  var username = localStorage.getItem('f4_username');
  if (username) {
    document.getElementById('xmpp-jid').textContent = username.toLowerCase() + '@freedoms4.org';
    document.getElementById('xmpp-loggedin').style.display = '';
  } else {
    document.getElementById('xmpp-loggedout').style.display = '';
  }
})();
</script>
