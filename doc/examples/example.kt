// Note: do not use any 'package' statements

import java.util.*

fun main(args: Array<String>) {
    var scanner = Scanner(System.`in`)
    val nTests = scanner.nextInt()
    for (i in 1..nTests) {
	    System.`out`.format("Hello %s!%n", scanner.next())
    }
}
