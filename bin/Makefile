all: runguard beep mkstemps inst-suid

inst-suid: runguard beep
	su root 'chown root.root runguard beep; chmod u+s runguard beep'

runguard:
	gcc -O2 -Wall runguard.c -o runguard

beep:
	gcc -O2 -Wall beep.c -o beep

mkstemps:
	gcc -O2 -Wall mkstemps.c -o mkstemps


