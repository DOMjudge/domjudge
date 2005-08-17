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

# Generate language specific config files from global config
config:
	$(MAKE) -C etc config

# Build everything that needs building (C programs)
build: config

# Interactively installs system as far as possible
install: build install_scripts
install_scripts: $(TEMPFILE)
	bin/make_passwords.sh   install
	bin/make_directories.sh install

# Generate documentation
docs: config
	$(MAKE) -C doc docs

# Restore system to completely fresh, uninstalled state
# Be careful: removes all possible contest data!!
distclean: distclean_scripts
distclean_scripts: $(TEMPFILE)
	bin/make_passwords.sh   distclean
	bin/make_directories.sh distclean
	rm -f $(TEMPFILE)

$(REC_TARGETS): %:
	for dir in $(SUBDIRS) ; do $(MAKE) -C $$dir $@ || exit 1 ; done

.PHONY: dvi documentation install_scripts distclean_scripts
