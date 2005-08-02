# Main Makefile
#
# $Id$
#
# From here all (subdir) Make targets can and _should_ be called to
# ensure proper inclusion of globally defined variables.

# Global Makefile definitions
export TOPDIR = $(PWD)
include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = bin etc lib doc submit judge www sql test-programs
REC_TARGETS = build install clean distclean

# Default targets
all: config build docs

# Build everything that needs building (C programs)
build: config

# Generate language specific config files from global config
config:
	$(MAKE) -C etc config

# Generate passwords and modify all relevant instances
gen_passwd: $(TEMPFILEDIR)/tempfile | passwd_perms
	@bin/gen_passwords.sh

# Check files containing sensitive password info for permissons
PASSWD_FILES = etc/passwords.php sql/mysql_privileges.sql
passwd_perms:
	@echo "WARNING ABOUT PASSWORD SECURITY:"
	@echo ""
	@echo "The passwords that are about to be generated/asked are stored in"
	@echo "plain-text in the following files:"
	@echo "    $(PASSWD_FILES)"
	@echo ""
	@echo "Protect these files carefully with the correct permissions and do not"
	@echo "choose a password equal to a sensitive existing one!"
	@echo ""
	@for f in $(PASSWD_FILES) ; do \
		if find . -perm +0077 | grep "\./$$f$$" >& /dev/null ; then \
			echo "WARNING: '$$f' found with group/other permissions!" ; \
			echo "WARNING: Check that permissions are set correctly!" ; \
		fi ; \
	done

# Generate documentation
docs: config
	$(MAKE) -C doc docs

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: dvi documentation gen_passwd
