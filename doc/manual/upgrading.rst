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

Since version 7.0 DOMjudge uses Doctrine migrations to upgrade the
database schema. This means that if you run a DOMjudge version before
7.0, you first need to complete an upgrade to 7.0 before upgrading to
a newer version.

If you have any active contests, we recommend to run "Refresh
scoreboard cache" from the DOMjudge web interface after the upgrade.

