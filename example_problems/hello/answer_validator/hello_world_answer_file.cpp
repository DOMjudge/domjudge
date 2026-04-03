#include <cstddef>
#include <iostream>
#include <regex>
#include <string>
 
int main()
{
    std::string line;
    int lines = 0;
    std::string expected = "hello world!";

    while (std::getline(std::cin, line)) {
        lines += 1;
        if (line.compare(expected)) {
            exit 43;
        }
    }

    if (lines != 1) {
        exit 43;
    }

    exit 42;
}
