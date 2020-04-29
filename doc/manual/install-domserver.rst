Installation of the DOMserver
=============================

The DOMjudge server (short DOMserver) is the central entity that runs
the DOMjudge web interface and API that teams, jury members and the
judgehosts connect to.

.. _domserver_requirements:

Requirements
------------

System requirements
```````````````````
* The operating system is Linux or another Unix variant. DOMjudge has mostly
  been tested with Debian and Ubuntu, but should work on other environments.
* It is probably necessary that you have root access to be able to install
  the necessary components, but it's not required for actually running the
  DOMserver.
* A TCP/IP network which connects the DOMserver and the judgehosts, and
  DOMjudge and the team workstations. All of these machines only need HTTP(S)
  access to the DOMserver.

Software requirements
`````````````````````
* A web server with support for PHP >= 7.2.5 and the ``mysqli``, ``curl``, ``gd``,
  ``mbstring``, ``intl``, ``zip``, ``xml`` and ``json`` extensions for PHP.
* MySQL or MariaDB database. This can be on the same machine, but for
  advanced setups can also run on a dedicated machine.
* An NTP daemon, for keeping the clocks between jury system and team
  workstations in sync.

For your convenience, the following command will install the necessary
software on the DOMjudge server as mentioned above when using Debian
GNU/Linux, or one of its derivative distributions like Ubuntu::

  sudo apt install acl zip unzip mariadb-server apache2 \
        php php-fpm php-gd php-cli php-intl php-mbstring php-mysql \
        php-curl php-json php-xml php-zip composer ntp

The following command can be used on RedHat Enterprise Linux, and related
distributions like CentOS and Fedora::

  sudo yum install acl zip unzip mariadb-server httpd \
        php-gd php-cli php-intl php-mbstring php-mysqlnd \
        php-xml php-zip composer ntp

Installation
------------
The DOMjudge build/install system consists of a ``configure``
script and makefiles, but when installing it, some more care has to be
taken than simply running ``./configure && make && make install``.

After installing the required software as described above, run configure.
In this example to install DOMjudge in the directory ``domjudge`` under
your home directory::

  ./configure --prefix=$HOME/domjudge
  make domserver
  sudo make install-domserver

Note that root privileges are required to set permissions and user and
group ownership of password files and a few directories. If you run
the installation targets as non-root, you will be shown how to perform
these steps manually.

Database configuration
----------------------
DOMjudge uses a MySQL or MariaDB database server for information storage.
Where this document talks about MySQL, it can be understood to also apply
to MariaDB.

Installation of the database is done with ``bin/dj_setup_database``.
For this, you need an installed and configured MySQL server and
administrator access to it. Run::

  dj_setup_database genpass
  dj_setup_database [-u <mysql admin user>] [-p <password>|-r] install

This first creates the DOMjudge database credentials file
``etc/dbpasswords.secret`` if it does not exist already.

Then it creates the database and user and inserts some
default/example data into the domjudge database. The option
``-r`` will prompt for a password for mysql; when no user is
specified, the mysql client will try to read
credentials from ``$HOME/.my.cnf`` as usual. The command
``uninstall`` can be passed to ``dj_setup_database`` to
remove the DOMjudge database and users; *this deletes all data*!

The script also creates the initial "admin" user with password
stored in ``etc/initial_admin_password.secret``.

Web server configuration
------------------------
For the web interface, you need to have a web server (e.g. nginx or Apache)
installed on the DOMserver and made sure that PHP correctly works
with it. Refer to the documentation of your web server and PHP for
details. In the examples below, replace ``7.3`` with the PHP version
you're installing.

To configure the Apache web server for DOMjudge, use the Apache
configuration snippet from ``etc/apache.conf``. It contains
examples for configuring the DOMjudge pages with an alias directive,
or as a virtualhost, optionally with TLS; it also contains PHP and security
settings. Reload the web server for changes to take effect.

::

  ln -s <DOMSERVER_INSTALL_PATH>/etc/apache.conf /etc/apache2/conf-available/domjudge.conf
  ln -s <DOMSERVER_INSTALL_PATH>/etc/domjudge-fpm.conf /etc/php/7.3/fpm/pool.d/domjudge.conf
  a2enmod proxy_fcgi setenvif rewrite
  a2enconf php7.3-fpm domjudge
  # Edit the file /etc/apache2/conf-available/domjudge.conf and
  # /etc/php/7.3/fpm/pool.d/domjudge.conf to your needs
  service php7.3-fpm reload
  service apache2 reload

An nginx webserver configuration snippet is also provided in
``etc/nginx-conf``.  You still need ``htpasswd`` from ``apache2-utils``
though. To use this configuration, perform the following steps::

  ln -s <DOMSERVER_INSTALL_PATH>/etc/nginx-conf /etc/nginx/sites-enabled/domjudge
  ln -s <DOMSERVER_INSTALL_PATH>/etc/domjudge-fpm.conf /etc/php/7.3/fpm/pool.d/domjudge.conf
  # Edit the files /etc/nginx/sites-enabled/domjudge and
  # /etc/php/7.3/fpm/pool.d/domjudge.conf to your needs
  service php7.3-fpm reload
  service nginx reload

The judgehosts connect to DOMjudge via the DOMjudge API so need
to be able to access at least this part of the web interface.

Log in to DOMjudge
------------------
The DOMserver should now be operational. You can access the web application
at your configured base URL. There's an ``admin`` user with initial password
found in ``etc/initial_admin_password.secret``.

You can continue now with
:doc:`installing one or more judgehosts <install-judgehost>`.
