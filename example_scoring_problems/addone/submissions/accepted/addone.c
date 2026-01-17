/* Correct solution using long long for large numbers
 * @EXPECTED_SCORE@: 60
 */
#include <stdio.h>

int main() {
    long long n;
    scanf("%lld", &n);
    printf("%lld\n", n + 1);
    return 0;
}
