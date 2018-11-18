<?php declare(strict_types=1);
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
            <a class="nav-link" href="./"><i class="fas fa-home"></i> Home</a>
          </li>
      <li class="nav-item<?=($pagename === 'problems.php'?' active':'')?>">
<?php if ($started): ?>
            <a class="nav-link" href="problems.php"><i class="fas fa-book-open"></i> Problemset</a>
<?php else: ?>
            <a class="nav-link disabled"><i class="fas fa-book-open"></i> Problemset</a>
<?php endif; ?>
          </li>
<?php if (have_printing()): ?>
      <li class="nav-item<?=($pagename === 'print.php'?' active':'')?>">
            <a class="nav-link" href="print.php"><i class="fas fa-file-alt"></i> Print</a>
      </li>
<?php endif; ?>
      <li class="nav-item<?=($pagename === 'scoreboard.php'?' active':'')?>">
            <a class="nav-link" href="scoreboard.php"><i class="fas fa-list-ol"></i> Scoreboard</a>
          </li>
      <li class="nav-item">
          <a class="nav-link" href="https://docs" target="_blank"><i class="fas fa-book"></i> Docs</a>
      </li>
      <li class="nav-item">
          <a class="nav-link" href="team-manual.pdf"><i class="fas fa-file-pdf"></i> Team manual</a>
      </li>
<?php if (checkrole('jury') || checkrole('balloon')): ?>
          <li class="nav-item">
            <a class="nav-link" href="../jury"><i class="fas fa-arrow-right"></i> Jury</a>
      </li>
<?php endif; ?>
         </ul>
      </div>

<?php if ($started): ?>
      <div id="submitbut"><a class="nav-link justify-content-center" href="submit.php"><button type="button" class="btn btn-success btn-sm"><i class="fas fa-cloud-upload-alt"></i> Submit</button></a></div>
<?php else: ?>
      <div id="submitbut"><a class="nav-link justify-content-center"><button type="button" class="btn btn-success btn-sm disabled"><i class="fas fa-cloud-upload-alt"></i> Submit</button></a></div>
<?php endif; ?>

<div id="logoutbut"><a class="nav-link justify-content-center" href="../logout"><button type="button" class="btn btn-outline-info btn-sm" onclick="return confirmLogout();"><i class="fas fa-sign-out-alt"></i> Logout</button></a></div>

<?php putClock(); ?>
    </nav>
<?php putProgressBar(-9); ?>
