%define soversion 3.1.0
%define soversion_major 3

Name: libcgroup
Summary: Tools and libraries to control and monitor control groups
Group: System Environment/Libraries
Version: 3.1.0
Release:        1%{?dist}
License: LGPLv2+
URL: http://libcg.sourceforge.net/
Source0: http://downloads.sourceforge.net/libcg/%{name}-%{version}.tar.bz2
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildRequires: pam-devel
BuildRequires: byacc
BuildRequires: flex
BuildRequires: coreutils
Requires(pre): shadow-utils
Requires(post): chkconfig, /sbin/service
Requires(preun): /sbin/chkconfig

%description
Control groups infrastructure. The tools and library help manipulate, control,
administrate and monitor control groups and the associated controllers.

%package pam
Summary: A Pluggable Authentication Module for libcgroup
Group: System Environment/Base
Requires: libcgroup = %{version}-%{release}

%description pam
Linux-PAM module, which allows administrators to classify the user's login
processes to pre-configured control group.

%package devel
Summary: Development libraries to develop applications that utilize control groups
Group: Development/Libraries
Requires: libcgroup = %{version}-%{release}

%description devel
It provides API to create/delete and modify cgroup nodes. It will also in the
future allow creation of persistent configuration for control groups and
provide scripts to manage that configuration.

%prep
%setup -q

%build
%configure --bindir=/bin --sbindir=/sbin --libdir=%{_libdir} --enable-initscript-install --enable-pam-module-dir=/%{_lib}/security

make %{?_smp_mflags}


%install
rm -rf $RPM_BUILD_ROOT
make DESTDIR=$RPM_BUILD_ROOT install

# install config files
mkdir -p $RPM_BUILD_ROOT/%{_sysconfdir}/sysconfig
cp samples/config/cgred.conf $RPM_BUILD_ROOT/%{_sysconfdir}/sysconfig/cgred.conf
cp samples/config/cgconfig.sysconfig $RPM_BUILD_ROOT/%{_sysconfdir}/sysconfig/cgconfig
cp samples/config/cgconfig.conf $RPM_BUILD_ROOT/%{_sysconfdir}/cgconfig.conf
cp samples/config/cgrules.conf $RPM_BUILD_ROOT/%{_sysconfdir}/cgrules.conf
cp samples/config/cgsnapshot_blacklist.conf $RPM_BUILD_ROOT/%{_sysconfdir}/cgsnapshot_blacklist.conf

# sanitize pam module, we need only pam_cgroup.so
mv -f $RPM_BUILD_ROOT/%{_lib}/security/pam_cgroup.so.*.*.* $RPM_BUILD_ROOT/%{_lib}/security/pam_cgroup.so
rm -f $RPM_BUILD_ROOT/%{_lib}/security/pam_cgroup.la $RPM_BUILD_ROOT/%{_lib}/security/pam_cgroup.so.*

# move the libraries  to /
mkdir -p $RPM_BUILD_ROOT/%{_lib}
mv -f $RPM_BUILD_ROOT/%{_libdir}/libcgroup.so.%{soversion} $RPM_BUILD_ROOT/%{_lib}
rm -f $RPM_BUILD_ROOT/%{_libdir}/libcgroup.so.%{soversion_major}
ln -sf libcgroup.so.%{soversion} $RPM_BUILD_ROOT/%{_lib}/libcgroup.so.%{soversion_major}
ln -sf ../../%{_lib}/libcgroup.so.%{soversion} $RPM_BUILD_ROOT/%{_libdir}/libcgroup.so
rm -f $RPM_BUILD_ROOT/%{_libdir}/*.la

%clean
rm -rf $RPM_BUILD_ROOT

%pre
getent group cgred >/dev/null || groupadd cgred

%post
/sbin/ldconfig
/sbin/chkconfig --add cgred
/sbin/chkconfig --add cgconfig

%preun
if [ $1 = 0 ]; then
    /sbin/service cgred stop > /dev/null 2>&1 || :
    /sbin/service cgconfig stop > /dev/null 2>&1 || :
    /sbin/chkconfig --del cgconfig
    /sbin/chkconfig --del cgred
fi

%postun -p /sbin/ldconfig

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/sysconfig/cgred.conf
%config(noreplace) %{_sysconfdir}/sysconfig/cgconfig
%config(noreplace) %{_sysconfdir}/cgconfig.conf
%config(noreplace) %{_sysconfdir}/cgrules.conf
%config(noreplace) %{_sysconfdir}/cgsnapshot_blacklist.conf
/%{_lib}/libcgroup.so.*
%attr(2755, root, cgred) /bin/cgexec
/bin/cgclassify
/bin/cgcreate
/bin/cgget
/bin/cgset
/bin/cgdelete
/bin/lscgroup
/bin/lssubsys
/sbin/cgconfigparser
/sbin/cgrulesengd
/bin/cgsnapshot
%attr(0644, root, root) %{_mandir}/man1/*
%attr(0644, root, root) %{_mandir}/man5/*
%attr(0644, root, root) %{_mandir}/man8/*
%attr(0755,root,root) %{_initrddir}/cgconfig
%attr(0755,root,root) %{_initrddir}/cgred

%doc COPYING INSTALL README_daemon

%files pam
%defattr(-,root,root,-)
%attr(0755,root,root) /%{_lib}/security/pam_cgroup.so
%doc COPYING INSTALL

%files devel
%defattr(-,root,root,-)
%{_includedir}/libcgroup.h
%{_includedir}/libcgroup/*.h
%{_libdir}/libcgroup.*
/%{_libdir}/pkgconfig/libcgroup.pc
%doc COPYING INSTALL


%changelog
* Tue Nov  2 2010 Jan Safranek <jsafrane@redhat.com> 0.37.rc1-1
- Add cgsnapshot
* Thu Feb 18 2010 Dhaval Giani <dhaval.giani@gmail.com> 0.36.rc1-1
- Add pkgconfig file
* Tue Jan 19 2010 Balbir Singh <balbir@linux.vnet.ibm.com> 0.35.1
- Integrate Jan's fixes for distributing cgget and initscripts
* Thu Oct 22 2009 Jan Safranek <jsafrane@redhat.com> 0.34-1
- Update to latest upstream
- Split PAM module to separate subpackage
* Tue Feb 24 2009 Balbir Singh <balbir@linux.vnet.ibm.com> 0.33-1
- Update to 0.33, spec file changes to add Makefiles and pam_cgroup module
* Fri Oct 10 2008 Dhaval Giani <dhaval@linux.vnet.ibm.com> 0.32-1
- Update to latest upstream
* Thu Sep 11 2008 Dhaval Giani <dhaval@linux-vnet.ibm.com> 0.31-1
- Update to latest upstream
* Sat Aug 2 2008 Dhaval Giani <dhaval@linux.vnet.ibm.com> 0.1c-3
- Change release to fix broken upgrade path
* Wed Jun 11 2008 Dhaval Giani <dhaval@linux.vnet.ibm.com> 0.1c-1
- Update to latest upstream version
* Tue Jun 3 2008 Balbir Singh <balbir@linux.vnet.ibm.com> 0.1b-3
- Add post and postun. Also fix Requires for devel to depend on base n-v-r
* Sat May 31 2008 Balbir Singh <balbir@linux.vnet.ibm.com> 0.1b-2
- Fix makeinstall, Source0 and URL (review comments from Tom)
* Mon May 26 2008 Balbir Singh <balbir@linux.vnet.ibm.com> 0.1b-1
- Add a generatable spec file
* Tue May 20 2008 Balbir Singh <balbir@linux.vnet.ibm.com> 0.1-1
- Get the spec file to work
* Tue May 20 2008 Dhaval Giani <dhaval@linux.vnet.ibm.com> 0.01-1
- The first version of libcg
