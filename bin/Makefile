all:
	gcc -O2 -Wall runguard.c -o runguard
	gcc -O2 -Wall beep.c -o beep
	su root 'chown root.root runguard beep; chmod u+s runguard beep'
