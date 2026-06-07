+++
draft = false
title = 'Email Account'
+++

<div id="email-loggedin" style="display:none">
  <p><strong>Email ID:</strong> <span id="email-address"></span></p>
  <p><strong>Password:</strong> Use the same password that you used during registration.</p>
</div>

<div id="email-loggedout" style="display:none">
  <p>Please <a href="/signup/">sign up</a> to create an email account.</p>
</div>

<script>
(function () {
  var username = localStorage.getItem('f4_username');
  if (username) {
    document.getElementById('email-address').textContent = username + '@freedoms4.org';
    document.getElementById('email-loggedin').style.display = '';
  } else {
    document.getElementById('email-loggedout').style.display = '';
  }
})();
</script>
