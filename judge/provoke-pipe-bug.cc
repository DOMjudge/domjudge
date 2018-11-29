#include <iostream>

using namespace std;

int main() {
	cout << "foobar" << endl;
	for (int k = 0; k < 42*23; k++) {
		cout << k + k*k << ' ';
	}
	cout << '\n';
	return 0;
}
