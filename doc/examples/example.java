import java.util.*;

class Main {
	public static void main(String[] args) {
		Scanner scanner = new Scanner(System.in);
		int nTests = scanner.nextInt();

		for (int i = 0; i < nTests; i++) {
			String name = scanner.next();
			System.out.println("Hello " + name + "!");
		}
	}
}
