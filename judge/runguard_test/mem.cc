#include <iostream>

int main(int argc, char **argv) {
	int byteCount = atoi(argv[1]);
	char *mem = new char[byteCount];
	for (int i = 0; i < byteCount; i += 2048) {
		mem[i] = (char) i*i;
	}
	std::cout << "mem = " << byteCount << std::endl;
}
