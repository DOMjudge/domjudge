using namespace std;

#include <iostream>
#include <string>

int main()
{
	int i, n;
	string name;

	cin >> n;
	for (i = 0; i < n; i++) {
		cin >> name;
		cout << "Hello " << name << "!" << endl;
	}

	return 0;
}
