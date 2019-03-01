</main>

<?php
/**
 * Common page footer
 */

// TODO: still used in combined_scoreboard. Refactor them and remove this

if (!defined('DOMJUDGE_VERSION')) {
    die("DOMJUDGE_VERSION not defined.");
}

if (DEBUG & DEBUG_TIMINGS) {
    echo "<p>";
    totaltime();
    echo "</p>";
} ?>

<script>
    $(function() {
        $('#cid').on('change', function() {
            var contestId = $(this).val();
            window.location = 'change-contest/' + contestId;
        });
        if ( 'Notification' in window ) {
            $('#notify').show();
        }
<?php if (isset($refresh)): ?>
        $('#refresh-toggle').on('click', function () {
            toggleRefresh('<?=$refresh['url']?>', <?=$refresh['after']?>);
        });
<?php endif; ?>
    });
<?php if (isset($refresh) && $refresh_cookie): ?>
    enableRefresh('<?=$refresh['url']?>', <?=$refresh['after']?>);
<?php endif; ?>
</script>
</body>
</html>
