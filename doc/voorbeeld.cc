using namespace std;

#include <iostream>
#include <string>

int main()
{
    int aantaltests, test;
    string naam;

    cin >> aantaltests;

    for(test=1; test<=aantaltests; test++) {
        cin >> naam;
        cout << "Hallo " << naam << "!\n";
    }
    
    return 0;
}
