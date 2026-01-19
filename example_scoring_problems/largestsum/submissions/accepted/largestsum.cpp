// @EXPECTED_RESULTS@: CORRECT
// @EXPECTED_SCORE@: 100
#include <iostream>
#include <algorithm>
using namespace std;

int main() {
    int n;
    cin >> n;
    long long maxSum, currSum;
    cin >> maxSum;
    currSum = maxSum;
    for (int i = 1; i < n; i++) {
        long long x;
        cin >> x;
        currSum = max(x, currSum + x);
        maxSum = max(maxSum, currSum);
    }
    cout << maxSum << endl;
    return 0;
}
