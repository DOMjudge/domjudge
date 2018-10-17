</main>

<?php
/**
 * Common page footer
 */
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
            document.forms['selectcontestform'].submit();
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
