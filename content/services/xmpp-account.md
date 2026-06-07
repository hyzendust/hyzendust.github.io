+++
draft = false
title = 'XMPP Account'
+++

<div id="xmpp-loggedin" style="display:none">
  <p><strong>JID:</strong> <span id="xmpp-jid"></span></p>
  <p><strong>Password:</strong> Use the same password that you used during registration.</p>
</div>

<div id="xmpp-loggedout" style="display:none">
  <p>Please <a href="/signup/">sign up</a> to create an XMPP account.</p>
</div>

<script>
(function () {
  var username = localStorage.getItem('f4_username');
  if (username) {
    document.getElementById('xmpp-jid').textContent = username + '@freedoms4.org';
    document.getElementById('xmpp-loggedin').style.display = '';
  } else {
    document.getElementById('xmpp-loggedout').style.display = '';
  }
})();
</script>
