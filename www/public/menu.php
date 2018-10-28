<?php declare(strict_types=1);
$fdata = calcFreezeData($cdata);
$started = checkrole('jury') || $fdata['started'];
?>
<nav class="navbar navbar-expand-md navbar-light bg-light fixed-top">

      <a class="navbar-brand hidden-sm-down" href="./">DOMjudge</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#menuDefault" aria-controls="menuDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="menuDefault">
        <ul class="navbar-nav mr-auto">
      <li class="nav-item<?=($pagename === 'index.php'?' active':'')?>">
            <a class="nav-link" href="./"><i class="fas fa-list-ol"></i> Scoreboard</a>
          </li>
      <li class="nav-item<?=($pagename === 'problems.php'?' active':'')?>">
<?php if ($started): ?>
            <a class="nav-link" href="problems.php"><i class="fas fa-book-open"></i> Problemset</a>
<?php else: ?>
            <a class="nav-link disabled"><i class="fas fa-book-open"></i> Problemset</a>
<?php endif; ?>
          </li>
<?php if (checkrole('team')): ?>
          <li class="nav-item">
            <a class="nav-link" href="../team/"><i class="fas fa-arrow-right"></i> Team</a>
      </li>
<?php endif; ?>
<?php if (checkrole('jury') || checkrole('balloon')): ?>
          <li class="nav-item">
            <a class="nav-link" href="../jury"><i class="fas fa-arrow-right"></i> Jury</a>
      </li>
<?php endif; ?>
         </ul>
      </div>
<?php
logged_in(); // fill userdata

if (!logged_in()) {
    echo '<div id="loginbut"><a class="nav-link justify-content-center" href="../login"><button type="button" class="btn btn-info btn-sm"><i class="fas fa-sign-in-alt"></i> Login</button></a></div>';
} else {
    echo '<div id="logoutbut"><a class="nav-link justify-content-center" href="../logout"><button type="button" class="btn btn-outline-info btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</button></a></div>';
}

putClock();
?>
    </nav>
<?php putProgressBar(-9); ?>
