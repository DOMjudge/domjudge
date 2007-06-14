import java.io.*;

class Main {
	public static BufferedReader in;

	public static void main(String[] args) throws Exception {
		int ntests, test;
		String name;
        
		in = new BufferedReader( new InputStreamReader(System.in) );

		ntests = Integer.parseInt(in.readLine());

		for(test=1; test<=ntests; test++) {
			name = in.readLine();
			System.out.println("Hello "+name+"!");
		}
	}
}
