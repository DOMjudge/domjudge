#!/bin/bash
# $Id$
#
# Script to generate a minimal chroot environment with Sun Java
# support to allow for Java programs to run in a chroot.
#
# This script downloads and installs a Debian unstable base system.
# Minimum requirements: a Linux system with glibc >= 2.3, wget, ar and
# a POSIX shell in /bin/sh. About 250 MB disk space is needed. It must
# be run as root and will install the Debian debootstrap package.

# Abort when a single command fails:
set -e

# Read command-line parameters:
CHROOTDIR=$1
ARCH=$2

# List of possible architectures to install chroot for:
ARCHLIST="alpha,arm,hppa,i386,ia64,m86k,mips,mipsel,powerpc,s390,sparc"

# Debian packages to exclude during bootstrap process (comma separated):
EXCLUDEDEBS="adduser,apt-utils,aptitude,at,base-config,bsdmainutils,console-common,console-data,console-tools,cron,dhcp3-client,dhcp3-common,dmidecode,dselect,exim4,exim4-base,exim4-config,exim4-daemon-light,fdutils,groff-base,ifupdown,info,iptables,iputils-ping,klogd,laptop-detect,libconsole,libdb4.2,libdb4.3,libgnutls13,libncursesw5,libnewt0.52,libopencdk8,libpcap0.7,libpcap0.8,libpci2,libpcre3,libpopt0,libsigc++-1.2-5c2,libsigc++-2.0-0c2a,libssl0.9.7,libssl0.9.8,libtasn1-3,libwrap0,logrotate,mailx,makedev,man-db,manpages,modconf,modutils,nano,net-tools,netbase,netkit-inetd,nvi,openbsd-inetd,pciutils,ppp,pppconfig,pppoe,pppoeconf,procps,psmisc,sysklogd,tasksel,tasksel-data,tcpd,telnet,traceroute,wget,whiptail"

# Debian packages to include during bootstrap process (comma separated):
INCLUDEDEBS="debconf-utils"

# Debian packages to install after upgrade (space separated):
INSTALLDEBS="sun-java5-jre"

# Debian packages to remove after upgrade (space separated):
REMOVEDEBS="dselect"

# Debian mirror, modify to match closest mirror
DEBMIRROR="http://ftp.debian.org/debian"
#DEBMIRROR="http://ftp.us.debian.org/debian"
#DEBMIRROR="http://ftp.nl.debian.org/debian"

# To prevent (libc6) upgrade questions:
export DEBIAN_FRONTEND=noninteractive

function usage()
{
    echo "Usage: $0 <installdir> <architecture>"
    echo "Creates a chroot environment with Sun Java support using the"
    echo "Debian GNU/Linux distribution."
    echo
    echo "This script must be run as root, <installdir> the non-existing target"
    echo "location of the chroot and <architecture> one of the following:"
    echo "$ARCHLIST"
}

function error()
{
    echo "Error: $@"
    echo
    usage
    exit 1
}

if [ `id -u` != 0 ]; then
    echo "Warning: you probably need to run this program as root."
fi

[ -z "$CHROOTDIR" ] && error "No installation directory given."
[ -z "$ARCH" ]      && error "No architecture given."
[ -e "$CHROOTDIR" ] && error "'$CHROOTDIR' already exists, remove manually."

mkdir -p "$CHROOTDIR"
cd "$CHROOTDIR"
CHROOTDIR="$PWD"

if [ -f /etc/debian_version ]; then

	cd /
	apt-get install debootstrap

else
	mkdir "$CHROOTDIR/debootstrap"
	cd "$CHROOTDIR/debootstrap"

	DEBOOTDEB="debootstrap_0.3.3.2_all.deb"
	wget "$DEBMIRROR/pool/main/d/debootstrap/${DEBOOTDEB}"

	ar -x "$DEBOOTDEB"
	cd /
	zcat "$CHROOTDIR/debootstrap/data.tar.gz" | tar xv

	rm -rf "$CHROOTDIR/debootstrap"
fi

echo "Running debootstrap to install base system, this may take a while..."
/usr/sbin/debootstrap --include="$INCLUDEDEBS" --exclude="$EXCLUDEDEBS" \
	--arch "$ARCH" etch "$CHROOTDIR" "$DEBMIRROR"

rm -f "$CHROOTDIR/etc/resolv.conf"
cp /etc/resolv.conf /etc/hostname "$CHROOTDIR/etc" || true

cat > "$CHROOTDIR/etc/apt/sources.list" <<EOF
# Different releases (incl. optional security repository):

# Stable (etch)
deb $DEBMIRROR			etch		main non-free contrib
deb http://security.debian.org	etch/updates	main non-free contrib

# Testing
#deb $DEBMIRROR			testing		main non-free contrib
#deb http://security.debian.org	testing/updates	main non-free contrib

# Unstable
#deb $DEBMIRROR			unstable	main non-free contrib
EOF

cat > "$CHROOTDIR/etc/apt/apt.conf" <<EOF
APT::Get::Assume-Yes "true";
APT::Get::Force-Yes "false";
APT::Get::Purge "true";
APT::Get::AllowUnauthenticated "true";
Acquire::Retries "3";
Acquire::PDiffs "false";
DPkg::Options {"--no-debsig";}
EOF

mount -t proc proc "$CHROOTDIR/proc"

# Prevent perl locale warnings in the chroot:
export LC_ALL=C

chroot "$CHROOTDIR" /bin/bash -c debconf-set-selections <<EOF
passwd	passwd/root-password-crypted	password	
passwd	passwd/user-password-crypted	password	
passwd	passwd/root-password		password	
passwd	passwd/root-password-again	password	
passwd	passwd/user-password-again	password	
passwd	passwd/user-password		password	
passwd	passwd/shadow			boolean	true
passwd	passwd/username-bad		note	
passwd	passwd/password-mismatch	note	
passwd	passwd/username			string	
passwd	passwd/make-user		boolean	true
passwd	passwd/md5			boolean	false
passwd	passwd/user-fullname		string	
passwd	passwd/user-uid			string	
passwd	passwd/password-empty		note	
debconf	debconf/priority	select	high
debconf	debconf/frontend	select	Noninteractive
locales	locales/locales_to_be_generated	multiselect	
locales	locales/default_environment_locale	select	None
sun-java5-jre	sun-java5-jre/jcepolicy		note	
sun-java5-bin	shared/accepted-sun-dlj-v1-1	boolean	true
sun-java5-jre	shared/accepted-sun-dlj-v1-1	boolean	true
sun-java5-jre	sun-java5-jre/stopthread	boolean	true
sun-java5-bin	shared/present-sun-dlj-v1-1	note	
sun-java5-jre	shared/present-sun-dlj-v1-1	note	
sun-java5-bin	shared/error-sun-dlj-v1-1	error	
sun-java5-jre	shared/error-sun-dlj-v1-1	error	
EOF

chroot "$CHROOTDIR" /bin/bash -c "apt-get update && apt-get dist-upgrade"
chroot "$CHROOTDIR" /bin/bash -c "apt-get clean"
chroot "$CHROOTDIR" /bin/bash -c "apt-get install $INSTALLDEBS"
chroot "$CHROOTDIR" /bin/bash -c "apt-get remove $REMOVEDEBS"
chroot "$CHROOTDIR" /bin/bash -c "apt-get clean"

umount "$CHROOTDIR/proc"

exit 0
