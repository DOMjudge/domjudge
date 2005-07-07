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
gen_passwd: $(TEMPFILEDIR)/tempfile
	bin/gen_passwords.sh

# Generate documentation
docs:
	$(MAKE) -C doc docs

dvi:
	$(MAKE) -C doc dvi

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: dvi documentation gen_passwd
