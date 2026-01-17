// @EXPECTED_SCORE@: 100
import java.util.Scanner;

public class fibonacci {
    static final long MOD = 1_000_000_007L;

    static long[][] matmult(long[][] A, long[][] B) {
        long[][] C = new long[2][2];
        for (int i = 0; i < 2; i++)
            for (int j = 0; j < 2; j++)
                C[i][j] = (A[i][0] * B[0][j] + A[i][1] * B[1][j]) % MOD;
        return C;
    }

    static long fibonacci(long n) {
        if (n <= 1) return n;
        long[][] result = {{1, 0}, {0, 1}};
        long[][] M = {{1, 1}, {1, 0}};
        while (n > 0) {
            if (n % 2 == 1) result = matmult(result, M);
            M = matmult(M, M);
            n /= 2;
        }
        return result[0][1];
    }

    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        long n = sc.nextLong();
        System.out.println(fibonacci(n));
    }
}
