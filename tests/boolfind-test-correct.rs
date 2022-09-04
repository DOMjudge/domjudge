/*
 * Sample solution in rust for the "boolfind" interactive problem.
 *
 * @EXPECTED_RESULTS@: CORRECT
 */

use std::io;
use std::io::Write;

fn main() -> io::Result<()> {
    let mut buffer = String::new();
    io::stdin().read_line(&mut buffer)?;
    let t = buffer.trim().parse::<i32>().unwrap();
    for _case in 1..=t {
        let mut buffer = String::new();
        io::stdin().read_line(&mut buffer)?;
        let n: usize = buffer.trim().parse().unwrap();
        let mut a = 0;
        let mut b = n;
        while a < b {
            let i = a + (b-a)/2;
            println!("READ {}", i);
            io::stdout().flush().unwrap();
            let mut buffer = String::new();
            io::stdin().read_line(&mut buffer)?;
            match buffer.trim() {
                "true" => {
                    a = i+1;
                },
                "false" => {
                    b = i;
                },
                _ => panic!("unexpected input"),
            }
        }
        assert_eq!(a, b);
        println!("OUTPUT {}", a - 1);
        io::stdout().flush().unwrap();
    }
    Ok(())
}
