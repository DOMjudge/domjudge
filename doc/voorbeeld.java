import java.io.*;

class Main {
	public static BufferedReader in;

	public static void main(String[] args) throws Exception {
		int aantaltests, test;
		String naam;
        
		in = new BufferedReader( new InputStreamReader(System.in) );

		aantaltests = Integer.parseInt(in.readLine());

		for(test=1; test<=aantaltests; test++) {
			naam = in.readLine();
			System.out.print("Hallo "+naam+"!\n");
		}
	}
}
