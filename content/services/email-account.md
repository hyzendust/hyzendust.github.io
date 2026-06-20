+++
draft = false
title = 'Email'
weight = 1
registration-needed = true
+++

<div id="email-loggedin" style="display:none">
  <p><strong>Email ID:</strong> <span id="email-address"></span></p>
  <p><strong>Password:</strong> Use the same password that you used during registration.</p>
</div>
<div id="email-loggedout" style="display:none">
  <p>Big corporate email providers read and scan every message that passes through their servers. Your emails become training data and ad-targeting fuel.</p>
  <p>We provide this service because email privacy shouldn't be a premium feature, it should be the default! Your emails here aren't mined or sold. They aren't anyone's business but yours.</p>
  <p>Please <a href="/signup/">sign up</a> to have an email account.</p>
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
