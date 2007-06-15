import java.io.*;

class Main
{
	public static BufferedReader in;

	public static void main(String[] args) throws IOException
	{
		in = new BufferedReader(new InputStreamReader(System.in));

		int nTests = Integer.parseInt(in.readLine());

		for (int test=1; test <= nTests; test++) {
			String name = in.readLine();
			System.out.println("Hello "+name+"!");
		}
	}
}
