/* Solution that crashes (RTE) on large inputs but works for basic
 * Demonstrates partial scoring with runtime error
 * @EXPECTED_RESULTS@: WRONG-ANSWER
 * @EXPECTED_SCORE@: 25
 */
#include <stdio.h>
#include <stdlib.h>

int main() {
    long long n;
    scanf("%lld", &n);

    if (n <= 1) {
        printf("%lld\n", n);
        return 0;
    }

    /* Allocate array - will fail or behave badly for large n */
    if (n > 100) {
        /* Deliberately cause a crash for large inputs */
        int *p = NULL;
        *p = 42;  /* Segfault */
    }

    /* Simple iteration for small n */
    long long a = 0, b = 1;
    for (long long i = 2; i <= n; i++) {
        long long tmp = a + b;
        a = b;
        b = tmp;
    }

    printf("%lld\n", b);
    return 0;
}
