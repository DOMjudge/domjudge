// @EXPECTED_SCORE@: 100
import java.util.Scanner;

public class largestsum {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int n = sc.nextInt();
        long maxSum = sc.nextLong();
        long currSum = maxSum;
        for (int i = 1; i < n; i++) {
            long x = sc.nextLong();
            currSum = Math.max(x, currSum + x);
            maxSum = Math.max(maxSum, currSum);
        }
        System.out.println(maxSum);
    }
}
