Appendix: Quick installation checklist
======================================

.. note::

  This is not a replacement for the thorough installation
  instructions, but more a cheat-sheet for those who've already
  installed DOMjudge before and need a few hints. When in doubt, always
  consult the full installation instruction.

DOMserver
`````````
 * Install the MySQL- or MariaDB-server and set a root password for it.
 * Install either nginx or Apache and PHP.
 * Make sure PHP works for the web server and command line scripts.

 * Extract the source tarball and run
   ``./configure --with-baseurl=<url> && make domserver``.
 * Run ``sudo make install-domserver`` to install the system.

 * Install the MySQL database using e.g.
   ``bin/dj_setup_database -u root -r install``.

 * For Apache: add ``etc/apache.conf`` to your Apache configuration and
   add ``etc/domjudge-fpm.conf`` to your PHP FPM pool directory, edit
   it to your needs, reload web server::

     sudo ln -s <DOMSERVER_INSTALL_PATH>/etc/apache.conf /etc/apache2/conf-available/domjudge.conf
     sudo ln -s <DOMSERVER_INSTALL_PATH>/etc/domjudge-fpm.conf /etc/php/7.3/fpm/pool.d/domjudge.conf
     sudo a2enmod proxy_fcgi setenvif rewrite
     sudo a2enconf php7.3-fpm domjudge
     sudo service php7.3-fpm reload
     sudo service apache2 reload

 * For nginx: add ``etc/nginx-conf`` to your nginx configuration and
   add ``etc/domjudge-fpm.conf`` to your PHP FPM pool directory, edit
   it to your needs, reload web server::

     sudo ln -s <DOMSERVER_INSTALL_PATH>/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
     sudo ln -s <DOMSERVER_INSTALL_PATH>/etc/domjudge-fpm.conf /etc/php/7.3/fpm/pool.d/domjudge.conf
     sudo service php7.3-fpm reload
     sudo service nginx reload

 * Check that the web interface works (/team, /public and /jury).
 * Check that the API (/api) works and create credentials for the judgehosts.
 * Create teams, user accounts and add useful contest data.
 * Run the config checker in the jury web interface.

Judgehosts
``````````
 * Extract the source tarball and run
   ``./configure --with-baseurl=<url> && make judgehost``.
 * Run ``sudo make install-judgehost`` to install the system.

 * ``useradd -d /nonexistent -U -M -s /bin/false domjudge-run``
 * Add to ``/etc/sudoers.d/`` or append to ``/etc/sudoers`` the
   sudoers configuration as in ``etc/sudoers-domjudge``.
 * Set up cgroup support: enable kernel parameters in
   ``/etc/default/grub`` and reboot, then use
   ``misc-tools/create_cgroups`` to create cgroups for DOMjudge.
 * Put the right credentials in the file ``etc/restapi.secret``.


 * Create the pre-built chroot tree: ``sudo bin/dj_make_chroot``

 * Start the judge daemon: ``bin/judgedaemon``

Checking if it works
````````````````````
It should be done by now. As a check that (almost) everything works,
the set of test sources can be submitted on the DOMserver::

  cd tests
  make check

Then, in the main jury web interface, select the admin link
*judging verifier* to automatically verify most of the
test sources. Read the test sources for a description of
what should (not) happen.
