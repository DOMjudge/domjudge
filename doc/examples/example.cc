using namespace std;

#include <iostream>
#include <string>

int main()
{
	int ntests, test;
	string name;

	cin >> ntests;

	for (test=1; test <= ntests; test++) {
		cin >> name;
		cout << "Hello " << name << "!" << endl;
	}

	return 0;
}
