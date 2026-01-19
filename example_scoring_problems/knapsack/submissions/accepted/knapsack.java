// @EXPECTED_RESULTS@: CORRECT
// @EXPECTED_SCORE@: 100
import java.util.*;

public class knapsack {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        int capacity = sc.nextInt();
        int n = sc.nextInt();
        int[] w = new int[n], v = new int[n];
        for (int i = 0; i < n; i++) {
            w[i] = sc.nextInt();
            v[i] = sc.nextInt();
        }

        int[] dp = new int[capacity + 1];
        List<List<Integer>> parent = new ArrayList<>();
        for (int i = 0; i <= capacity; i++)
            parent.add(new ArrayList<>());

        for (int i = 0; i < n; i++) {
            for (int c = capacity; c >= w[i]; c--) {
                if (dp[c - w[i]] + v[i] > dp[c]) {
                    dp[c] = dp[c - w[i]] + v[i];
                    parent.set(c, new ArrayList<>(parent.get(c - w[i])));
                    parent.get(c).add(i);
                }
            }
        }

        List<Integer> selected = parent.get(capacity);
        System.out.println(selected.size());
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < selected.size(); i++) {
            if (i > 0) sb.append(' ');
            sb.append(selected.get(i));
        }
        System.out.println(sb);
    }
}
