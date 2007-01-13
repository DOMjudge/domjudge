# Main Makefile
#
# $Id$
#
# From here all (subdir) Make targets can and _should_ be called to
# ensure proper inclusion of globally defined variables.

# Define TOPDIR from shell command and _not_ $(PWD) because that gives
# pwd before changing to a 'make -C <dir>' <dir>:
export TOPDIR = $(shell pwd)

# Global Makefile definitions
include $(TOPDIR)/Makefile.global

# Subdirectories to recurse into for REC_TARGETS
SUBDIRS = bin etc lib doc submit judge www sql test-programs test-sources
REC_TARGETS = build install check clean distclean

# Default targets
all: config build docs

# Generate language specific config files from global config
config:
	$(MAKE) -C etc config

# Build everything that needs building (C programs)
build: config

# Interactively installs system as far as possible
install: build install_scripts .installed

.installed:
	touch .installed

install_scripts: $(TEMPFILE)
	bin/make_passwords.sh   install
	bin/make_directories.sh install

# Generate documentation
docs: config
	$(MAKE) -C doc docs

# Perform some checks
check: .installed

# Restore system to completely fresh, uninstalled state
# Be careful: removes all possible contest data!!
distclean: distclean_scripts

distclean_scripts: $(TEMPFILE)
	bin/make_passwords.sh   distclean
	bin/make_directories.sh distclean
	rm -f $(TEMPFILE)
	rm -f .installed

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: dvi documentation install_scripts distclean_scripts
