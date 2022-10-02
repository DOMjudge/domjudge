#include <iostream>
#include <string>
#include "message.hpp"

using namespace std;

message::message()
{
	data = string("Hello world!");
}

void message::print()
{
	cout << data << endl;
}
