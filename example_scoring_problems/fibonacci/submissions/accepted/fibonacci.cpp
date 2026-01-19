// @EXPECTED_RESULTS@: CORRECT
// @EXPECTED_SCORE@: 100
#include <iostream>
using namespace std;

const long long MOD = 1e9 + 7;

void matmult(long long A[2][2], long long B[2][2], long long C[2][2]) {
    long long temp[2][2];
    for (int i = 0; i < 2; i++)
        for (int j = 0; j < 2; j++)
            temp[i][j] = (A[i][0] * B[0][j] + A[i][1] * B[1][j]) % MOD;
    for (int i = 0; i < 2; i++)
        for (int j = 0; j < 2; j++)
            C[i][j] = temp[i][j];
}

long long fibonacci(long long n) {
    if (n <= 1) return n;
    long long result[2][2] = {{1, 0}, {0, 1}};
    long long M[2][2] = {{1, 1}, {1, 0}};
    while (n > 0) {
        if (n % 2 == 1) matmult(result, M, result);
        matmult(M, M, M);
        n /= 2;
    }
    return result[0][1];
}

int main() {
    long long n;
    cin >> n;
    cout << fibonacci(n) << endl;
    return 0;
}
