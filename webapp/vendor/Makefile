ifndef TOPDIR
TOPDIR=../..
endif
include $(TOPDIR)/Makefile.global

clean-l:
	rm -f autoload_runtime.php

autoload_runtime.php:
	composer $(subst 1,-q,$(QUIET)) dump-autoload -o -a -d ..

domserver: autoload_runtime.php
