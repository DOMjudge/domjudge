all: runguard beep mkstemps inst-suid

inst-suid: runguard beep
	su root 'chown root.root runguard beep; chmod u+s runguard beep'

runguard: runguard.c ../etc/config.h
	gcc -O2 -Wall runguard.c -o runguard

beep: beep.c
	gcc -O2 -Wall beep.c -o beep

mkstemps: mkstemps.c ../lib/mkstemps.h ../lib/basename.h
	gcc -O2 -Wall mkstemps.c -o mkstemps
