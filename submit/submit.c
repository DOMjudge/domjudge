#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <sys/wait.h>
#include <signal.h>

// #include "newjury.h"    // general structs

#define BACKLOG 32      // how many pending connections queue will hold
#define CONFIG_FILE "newjury.config"

void createServer();
void handleClient(int);
void loadOptions();
void processOption(char *, char *);

// helper functions
char *copystr(char *);
int copyint(char *, int *);
void logmsg (char *, ...);
void sigchld_handler(int);
char *trim(char *);
char *split(char *, char);

int yes=1;

FILE *results;
int server_fd, new_fd;          // listen on server_fd, new connection on new_fd
struct sockaddr_in server_addr; // my address information
struct sockaddr_in their_addr;  // connector's address information
int sin_size;

/*****************************************************************************/

// Global options.
int port = 9147;

char *ouremail;
char *smtphost;
int smtpdebug = 0;

// Info about supported languages.
int languages = 0;
char **language = NULL;

// Info about the problems.
int problems = 0;
struct problem_info *problem = NULL;
// my %problems;
// my %javaclassnames;
// my %inputnames;
// my %outputnames;
// my %runningtimes;

// Paths
char *testsetdir;
char *submitdir;

// Info about the contestants.
char *contestantsfile;
// my %contestants;
// my %addresses;
// my %passwords;

// Compile/run settings.
char *cc_options;
char *cppc_options;
char *javac_options;
char *javai_options;
char *runhugs_options;
char *run_options;

/*****************************************************************************/

int main(void)
{
    struct sigaction sa;
    int cpid;
    
    // Initialize
    loadOptions();
    
    results = fopen("results", "a");
    logmsg("server started");
    
    createServer();
    logmsg("listening on port %i", port);
    
    // reap all dead processes
    sa.sa_handler = sigchld_handler;
    sigemptyset(&sa.sa_mask);
    sa.sa_flags = SA_RESTART;
    if (sigaction(SIGCHLD, &sa, NULL) == -1) {
        perror("sigaction");
        exit(1);
    }
    
    // main accept() loop
    while(1) {
        sin_size = sizeof(struct sockaddr_in);
        if ((new_fd = accept(server_fd
                        , (struct sockaddr *)&their_addr, &sin_size)
            ) == -1) {
            perror("accept");
            continue;
        }
        
        // loadOptions();
        logmsg("incoming connection, spawning child");
        
        cpid = fork();
        
        if (cpid == -1) {
            perror("fork");
        } else if (cpid == 0) {
            // this is the child process
            
            logmsg("connection from %s", inet_ntoa(their_addr.sin_addr));
            
            close(server_fd); // child doesn't need the listener
            
// -- child function
            
            handleClient(new_fd);
            // compile()
            // run()
            // checkoutput()
            
            // Done.  The program is correct.
// --
            exit(0);
        }
        logmsg("spawned %05i", cpid);
        close(new_fd);
    }
    return 0;
}

/*****************************************************************************/

/***
 *  Open a listening socket on the localhost.
 *  
 *  global variables used:
 *      port
 *      server_addr
 *      server_fd
 */
void createServer()
{
    if ((server_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
        perror("socket");
        exit(1);
    }

    if (setsockopt(server_fd
                , SOL_SOCKET, SO_REUSEADDR, &yes, sizeof(int)) == -1) {
        perror("setsockopt");
        exit(1);
    }
    
    server_addr.sin_family = AF_INET;           // host byte order
    server_addr.sin_port = htons(port);         // short, network byte order
    server_addr.sin_addr.s_addr = INADDR_ANY;   // automatically fill with my IP
    memset(&(server_addr.sin_zero), '\0', 8);   // zero the rest of the struct
    
    if (bind(server_fd, (struct sockaddr *)&server_addr
                , sizeof(struct sockaddr)) == -1) {
        perror("bind");
        exit(1);
    }
    
    if (listen(server_fd, BACKLOG) == -1) {
        perror("listen");
        exit(1);
    }
}

/***
 *
 */
void handleClient(int client)
{
    if (send(client, "+hello, send submission info, then files", 40, 0) == -1)
        perror("send");
    close(new_fd);
}           

/***
 *  read all the configuration options in from file
 *  
 *  global variables used:
 *      config_file
 */
void loadOptions()
{
    FILE *options;
    int  linenr, linelen;
    char word[255];
    char *line, *ptr;
    
    if((options = fopen(CONFIG_FILE, "r")) == NULL) {
        perror("options");
        exit(1);
    }
    
    for(linenr = 1; !feof(options); linenr++)
    {
        word[0] = '\0';
        fgets(word, 255, options);
        line = word;
        
        // ignore any comment
        split(word, '#');
        
        // check its a possible length
        linelen = strlen(word);
        if (linelen < 2) {
            continue;
        }
        
        // split the string on the (first) '='
        if((ptr = split(line, '=')) == NULL) {
            // no '=' sign, than (try to) split of first word
            ptr = split(line, ' ');
        }
        
        line = trim(line);
        ptr = trim(ptr);
        
        processOption(line, ptr);
        
    }
    
    fclose(options);
}

/***
 *  TODO bla bla
 */
void processOption(char *id, char *val)
{
    // server port
    if(!strncasecmp(id, "port", 4)) {
        // no effect if already running!
        copyint(val, &port);
    
    // mail options
    } else if (!strncasecmp(id, "from", 4)){ 
        free(ouremail);
        ouremail = copystr(val);
    } else if (!strncasecmp(id, "smtphost", 8)){ 
        free(smtphost);
        smtphost = copystr(val);
    } else if (!strncasecmp(id, "smtpdebug", 9)){ 
        copyint(val, &smtpdebug);
    
    // languages
    } else if (!strncasecmp(id, "languages", 9)){ 
        if(language != NULL)
        {
            char *lang; int i;
            for(i = 0; (lang = language[i]) != NULL; i++) {
                printf("%i\n", lang);
                free(lang);
            }
            free(language);
        }
        
        copyint(val, &languages);
        language = (char **)calloc(languages, sizeof(char *));
        
    } else if (!strncasecmp(id, "language", 8)){ 
        if(languages < 1) {
            perror("LANGUAGES value to low or not set");
            return;
        }
        languages--;
        language[languages] = copystr(val);
    
    // problems
    } else if (!strncasecmp(id, "problems", 8)){ 
        if(problem != NULL)
        {
            struct problem_info *prob;
            int i;
            for(i = 0; (prob = (struct problem_info *)&problem[i]) != NULL; i++) {
                free(prob);
            }
            free(problem);
        }
        copyint(val, &problems);
        problem = (struct problem_info *)calloc
                        (problems, sizeof(struct problem_info *));
printf("NR PROBLEMS = %i\n", problems);
    } else if (!strncasecmp(id, "problem", 7)) { 
printf("PROBLEM = %s\n", val);
        
        char *word;
        
        if(problems < 1) {
            perror("PROBLEMS value to low or not set");
            return;
        }
        problems--;
        problem[problems] = (struct problem)calloc(1, sizeof(struct problem));
        word = split(val, ' ');
printf("PROBLEM name = %s\n", word);
        problem[problems].name = copystr(word);
        word = split(val, ' ');
printf("PROBLEM java class = %s\n", word);
        problem[problems].javaclass = copystr(word);
        word = split(val, ' ');
printf("PROBLEM input name = %s\n", word);
        problem[problems].inputname = copystr(word);
        word = split(val, ' ');
printf("PROBLEM output name = %s\n", word);
        problem[problems].outputname = copystr(word);
printf("PROBLEM rest = %s\n", var);
    // testset directory
    } else if (!strncasecmp(id, "testset", 7)){ 
        free(testsetdir);
        testsetdir = copystr(val);
    
    // contestants file
    } else if (!strncasecmp(id, "contestants", 11)){ 
        // TODO process it (... or later)
        free(contestantsfile);
        contestantsfile = copystr(val);
printf("CONTESTANTS = %s\n", contestantsfile);
    
    // submission directory
    } else if (!strncasecmp(id, "submissions", 7)){ 
        // TODO check that it exists
        free(submitdir);
        submitdir = copystr(val);
    
    // compiler options
    } else if (!strncasecmp(id, "cc_options", 10)){ 
        free(cc_options);
        cc_options = copystr(val);
    } else if (!strncasecmp(id, "cppc_options", 12)){ 
        free(cppc_options);
        cppc_options = copystr(val);
    } else if (!strncasecmp(id, "javac_options", 13)){ 
        free(javac_options);
        javac_options = copystr(val);
    } else if (!strncasecmp(id, "javai_options", 13)){ 
        free(javai_options);
        javai_options = copystr(val);
    } else if (!strncasecmp(id, "runhugs_options", 15)){ 
        free(runhugs_options);
        runhugs_options = copystr(val);
    
    // extra command to run prior to invocation of submitted program
    } else if (!strncasecmp(id, "run_options", 7)){ 
        free(run_options);
        run_options = copystr(val);
    }
}

/*****************************************************************************/
/***                            HELPER FUNCTIONS                           ***/
/*****************************************************************************/

/***
 *  return pointer to copy of null terminated string
 */
char *copystr(char *str)
{
    int mem_size = (strlen(str) + 1)* sizeof(char);
    
    if(!mem_size)
        return str;
    
    return memcpy((char*)malloc(mem_size), str, mem_size);
}

int copyint(char *str, int *to)
{
    int tmp;
    errno = 0;
    tmp = atoi(str);
    if(errno != 0) {
        perror("illegal number");
        return 0;
    }
    *to = tmp;
    return 1;
}

/***
 *  wrapper function around printf
 *  this way we always get a system time & procces id with the output
 */
void logmsg (char *mesg, ...)
{
    char buffer[128];
    struct tm *datetime;
    time_t curtime;
    va_list ap;
    
    curtime = time(NULL);
    datetime = localtime(&curtime);
    strftime(buffer, sizeof(buffer), "%a %b %d %k:%M:%S  %Y", datetime);
    fprintf(stderr, "[%s - %05i] ", buffer, getpid());
    
    va_start(ap, mesg);
    vfprintf(stderr, mesg, ap);
    va_end(ap);
    
    fprintf(stderr, "\n");
    
    fflush(stderr);
    free(datetime);
}

/***
 *  used to kill of (child) threads
 *  
 *  TODO: return the exit code
 */
void sigchld_handler(int s)
{
    int exitpid, exitcode;
    exitpid = wait(&exitcode);
    logmsg("reaped process %05i with exit code %i", exitpid, exitcode);
}

/***
 *  trim both the head and tail of a string
 *  
 *  white spaces at the end are all replaced with \0
 *  white spaces at the front are left in place
 *      (return pointer is simply a bit further in the string)
 */
char *trim(char *ptr)
{
    int len = strlen(ptr);
    char *end = &ptr[len] - 1;
    
    // safety catch
    if(ptr == NULL)
        return NULL;
    
    // first trim of the end
    while( *end == ' '
        || *end == '\t'
        || *end == '\n'
        || *end == '\r') {
        *end = '\0';
        end--;
        if(end < ptr)
            break;
    }
    
    // than trim the front
    while( *ptr == ' '
        || *ptr == '\t'
        || *ptr == '\n'
        || *ptr == '\r') {
        ptr++;
    }
    
    return ptr; 
}

/***
 *  split a string after the first occurence char 'c'
 *  
 *  pointer to the beginning of split of part is returned
 */
char *split(char *ptr, char c)
{
    char *loc;
    
    // safety catch
    if(ptr == NULL)
        return NULL;
    
    if((loc = strchr(ptr, c)) != NULL) {
        *loc = '\0';
        loc++;
    }
    
    return loc;
}

//  vim:ts=4:sw=4:et:
