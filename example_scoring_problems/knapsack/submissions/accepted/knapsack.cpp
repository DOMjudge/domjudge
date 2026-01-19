// @EXPECTED_RESULTS@: CORRECT
// @EXPECTED_SCORE@: 100
#include <iostream>
#include <vector>
using namespace std;

int main() {
    int capacity, n;
    cin >> capacity >> n;
    vector<int> w(n), v(n);
    for (int i = 0; i < n; i++)
        cin >> w[i] >> v[i];

    vector<int> dp(capacity + 1, 0);
    vector<vector<int>> parent(capacity + 1);

    for (int i = 0; i < n; i++) {
        for (int c = capacity; c >= w[i]; c--) {
            if (dp[c - w[i]] + v[i] > dp[c]) {
                dp[c] = dp[c - w[i]] + v[i];
                parent[c] = parent[c - w[i]];
                parent[c].push_back(i);
            }
        }
    }

    cout << parent[capacity].size() << endl;
    for (int i = 0; i < parent[capacity].size(); i++) {
        if (i > 0) cout << ' ';
        cout << parent[capacity][i];
    }
    cout << endl;
    return 0;
}
