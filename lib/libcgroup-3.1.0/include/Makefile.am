# Using 'nobase_', we what groups.h in /usr/include/libcgroup/ directory
nobase_include_HEADERS = libcgroup.h libcgroup/error.h libcgroup/init.h \
			 libcgroup/groups.h libcgroup/tasks.h \
			 libcgroup/iterators.h libcgroup/config.h \
			 libcgroup/log.h libcgroup/tools.h

if WITH_SYSTEMD
nobase_include_HEADERS += libcgroup/systemd.h
endif
