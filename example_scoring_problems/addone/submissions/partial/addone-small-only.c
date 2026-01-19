/* Partial solution - only handles small numbers (int overflow for large)
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 * @EXPECTED_SCORE@: 30
 */
#include <stdio.h>

int main() {
    int n;
    scanf("%d", &n);
    printf("%d\n", n + 1);
    return 0;
}
