#include <iostream>
#include <string>

using namespace std;

int main() {
	int ntests;
	string name;

	cin >> ntests;
	for (int i = 0; i < ntests; i++) {
		cin >> name;
		cout << "Hello " << name << "!" << endl;
	}
}
