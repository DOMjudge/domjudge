<?php
$fdata = calcFreezeData($cdata);
$started = checkrole('jury') || $fdata['started'];
?>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
      <a class="navbar-brand hidden-sm-down" href="./">DOMjudge</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#menuDefault" aria-controls="menuDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="menuDefault">
        <ul class="navbar-nav mr-auto">
      <li class="nav-item<?=($pagename === 'index.php'?' active':'')?>">
            <a class="nav-link" href="./"><span class="octicon octicon-home"></span> Home</a>
          </li>
      <li class="nav-item<?=($pagename === 'problems.php'?' active':'')?>">
<?php if ($started): ?>
            <a class="nav-link" href="problems.php"><span class="octicon octicon-book"></span> Problemset</a>
<?php else: ?>
            <a class="nav-link disabled"><span class="octicon octicon-book"></span> Problemset</a>
<?php endif; ?>
          </li>
<?php if (have_printing()): ?>
      <li class="nav-item<?=($pagename === 'print.php'?' active':'')?>">
            <a class="nav-link" href="print.php"><span class="octicon octicon-file-text"></span> Print</a>
      </li>
<?php endif; ?>
      <li class="nav-item<?=($pagename === 'scoreboard.php'?' active':'')?>">
            <a class="nav-link" href="scoreboard.php"><span class="octicon octicon-list-ordered"></span> Scoreboard</a>
          </li>
<?php if (checkrole('jury') || checkrole('balloon')): ?>
          <li class="nav-item">
            <a class="nav-link" href="../jury"><span class="octicon octicon-arrow-right"></span> Jury</a>
      </li>
<?php endif; ?>
         </ul>
      </div>

<?php if ($started): ?>
      <div id="submitbut"><a class="nav-link justify-content-center" href="submit.php"><button type="button" class="btn btn-success btn-sm"><span class="octicon octicon-cloud-upload"></span> Submit</button></a></div>
<?php else: ?>
      <div id="submitbut"><a class="nav-link justify-content-center"><button type="button" class="btn btn-success btn-sm disabled"><span class="octicon octicon-cloud-upload"></span> Submit</button></a></div>
<?php endif; ?>

<div id="logoutbut"><a class="nav-link justify-content-center" href="../logout"><button type="button" class="btn btn-outline-info btn-sm" onclick="return confirmLogout();"><span class="octicon octicon-sign-out"></span> Logout</button></a></div>

<?php putClock(); ?>
    </nav>
<?php putProgressBar(-9); ?>
