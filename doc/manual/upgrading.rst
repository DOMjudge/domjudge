Upgrading
=========

There is support to upgrade an existing DOMjudge installation to
a newer version.

.. warning::

  Before you begin, it is always advised to backup the DOMjudge
  database. We also advise to check the ``ChangeLog`` file for
  important changes.

Upgrading the filesystem installation is probably best done by
installing the new version of DOMjudge in a separate place and
transferring the configuration settings from the old version.

After upgrading the files, you can run ``dj_setup_database upgrade``
to migrate the database.

If you have any active contests, we recommend to run "Refresh
scoreboard cache" from the DOMjudge web interface after the upgrade.

Upgrading from pre-7.0 versions
-------------------------------
The upgrade procedure described above works from DOMjudge 7.0
and above. This means that if you run an older DOMjudge version,
you first need to complete an upgrade to 7.0 before upgrading to
a newer version. See https://github.com/DOMjudge/domjudge/tree/7.0/sql/upgrade
for instructions for upgrading to 7.0. When you have successfully
upgraded to 7.0, you can run the procedure above to reach the
current version.
