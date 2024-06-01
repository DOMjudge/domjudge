/* A Bison parser, made by GNU Bison 3.8.2.  */

/* Bison implementation for Yacc-like parsers in C

   Copyright (C) 1984, 1989-1990, 2000-2015, 2018-2021 Free Software Foundation,
   Inc.

   This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program.  If not, see <https://www.gnu.org/licenses/>.  */

/* As a special exception, you may create a larger work that contains
   part or all of the Bison parser skeleton and distribute that work
   under terms of your choice, so long as that work isn't itself a
   parser generator using the skeleton or a modified version thereof
   as a parser skeleton.  Alternatively, if you modify or redistribute
   the parser skeleton itself, you may (at your option) remove this
   special exception, which will cause the skeleton and the resulting
   Bison output files to be licensed under the GNU General Public
   License without this special exception.

   This special exception was added by the Free Software Foundation in
   version 2.2 of Bison.  */

/* C LALR(1) parser skeleton written by Richard Stallman, by
   simplifying the original so-called "semantic" parser.  */

/* DO NOT RELY ON FEATURES THAT ARE NOT DOCUMENTED in the manual,
   especially those whose name start with YY_ or yy_.  They are
   private implementation details that can be changed or removed.  */

/* All symbols defined below should begin with yy or YY, to avoid
   infringing on user name space.  This should be done even for local
   variables, as they might otherwise be expanded by user macros.
   There are some unavoidable exceptions within include files to
   define necessary library symbols; they are noted "INFRINGES ON
   USER NAME SPACE" below.  */

/* Identify Bison output, and Bison version.  */
#define YYBISON 30802

/* Bison version string.  */
#define YYBISON_VERSION "3.8.2"

/* Skeleton name.  */
#define YYSKELETON_NAME "yacc.c"

/* Pure parsers.  */
#define YYPURE 0

/* Push parsers.  */
#define YYPUSH 0

/* Pull parsers.  */
#define YYPULL 1




/* First part of user prologue.  */
#line 11 "parse.y"

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <libcgroup.h>
#include <libcgroup-internal.h>

int yylex(void);
extern int line_no;
extern char *yytext;

static void yyerror(const char *s)
{
	fprintf(stderr, "error at line number %d at %s:%s\n", line_no, yytext, s);
}

int yywrap(void)
{
	return 1;
}


#line 94 "parse.c"

# ifndef YY_CAST
#  ifdef __cplusplus
#   define YY_CAST(Type, Val) static_cast<Type> (Val)
#   define YY_REINTERPRET_CAST(Type, Val) reinterpret_cast<Type> (Val)
#  else
#   define YY_CAST(Type, Val) ((Type) (Val))
#   define YY_REINTERPRET_CAST(Type, Val) ((Type) (Val))
#  endif
# endif
# ifndef YY_NULLPTR
#  if defined __cplusplus
#   if 201103L <= __cplusplus
#    define YY_NULLPTR nullptr
#   else
#    define YY_NULLPTR 0
#   endif
#  else
#   define YY_NULLPTR ((void*)0)
#  endif
# endif

/* Use api.header.include to #include this header
   instead of duplicating it here.  */
#ifndef YY_YY_PARSE_H_INCLUDED
# define YY_YY_PARSE_H_INCLUDED
/* Debug traces.  */
#ifndef YYDEBUG
# define YYDEBUG 0
#endif
#if YYDEBUG
extern int yydebug;
#endif

/* Token kinds.  */
#ifndef YYTOKENTYPE
# define YYTOKENTYPE
  enum yytokentype
  {
    YYEMPTY = -2,
    YYEOF = 0,                     /* "end of file"  */
    YYerror = 256,                 /* error  */
    YYUNDEF = 257,                 /* "invalid token"  */
    ID = 258,                      /* ID  */
    MOUNT = 259,                   /* MOUNT  */
    GROUP = 260,                   /* GROUP  */
    PERM = 261,                    /* PERM  */
    TASK = 262,                    /* TASK  */
    ADMIN = 263,                   /* ADMIN  */
    NAMESPACE = 264,               /* NAMESPACE  */
    DEFAULT = 265,                 /* DEFAULT  */
    TEMPLATE = 266,                /* TEMPLATE  */
    SYSTEMD = 267                  /* SYSTEMD  */
  };
  typedef enum yytokentype yytoken_kind_t;
#endif
/* Token kinds.  */
#define YYEMPTY -2
#define YYEOF 0
#define YYerror 256
#define YYUNDEF 257
#define ID 258
#define MOUNT 259
#define GROUP 260
#define PERM 261
#define TASK 262
#define ADMIN 263
#define NAMESPACE 264
#define DEFAULT 265
#define TEMPLATE 266
#define SYSTEMD 267

/* Value type.  */
#if ! defined YYSTYPE && ! defined YYSTYPE_IS_DECLARED
union YYSTYPE
{
#line 36 "parse.y"

	char *name;
	char chr;
	int val;
	struct cgroup_dictionary *values;

#line 178 "parse.c"

};
typedef union YYSTYPE YYSTYPE;
# define YYSTYPE_IS_TRIVIAL 1
# define YYSTYPE_IS_DECLARED 1
#endif


extern YYSTYPE yylval;


int yyparse (void);


#endif /* !YY_YY_PARSE_H_INCLUDED  */
/* Symbol kind.  */
enum yysymbol_kind_t
{
  YYSYMBOL_YYEMPTY = -2,
  YYSYMBOL_YYEOF = 0,                      /* "end of file"  */
  YYSYMBOL_YYerror = 1,                    /* error  */
  YYSYMBOL_YYUNDEF = 2,                    /* "invalid token"  */
  YYSYMBOL_ID = 3,                         /* ID  */
  YYSYMBOL_MOUNT = 4,                      /* MOUNT  */
  YYSYMBOL_GROUP = 5,                      /* GROUP  */
  YYSYMBOL_PERM = 6,                       /* PERM  */
  YYSYMBOL_TASK = 7,                       /* TASK  */
  YYSYMBOL_ADMIN = 8,                      /* ADMIN  */
  YYSYMBOL_NAMESPACE = 9,                  /* NAMESPACE  */
  YYSYMBOL_DEFAULT = 10,                   /* DEFAULT  */
  YYSYMBOL_TEMPLATE = 11,                  /* TEMPLATE  */
  YYSYMBOL_SYSTEMD = 12,                   /* SYSTEMD  */
  YYSYMBOL_13_ = 13,                       /* '{'  */
  YYSYMBOL_14_ = 14,                       /* '}'  */
  YYSYMBOL_15_ = 15,                       /* '='  */
  YYSYMBOL_16_ = 16,                       /* ';'  */
  YYSYMBOL_YYACCEPT = 17,                  /* $accept  */
  YYSYMBOL_start = 18,                     /* start  */
  YYSYMBOL_default = 19,                   /* default  */
  YYSYMBOL_default_conf = 20,              /* default_conf  */
  YYSYMBOL_group = 21,                     /* group  */
  YYSYMBOL_group_name = 22,                /* group_name  */
  YYSYMBOL_group_conf = 23,                /* group_conf  */
  YYSYMBOL_template = 24,                  /* template  */
  YYSYMBOL_template_conf = 25,             /* template_conf  */
  YYSYMBOL_template_task_or_admin = 26,    /* template_task_or_admin  */
  YYSYMBOL_namevalue_conf = 27,            /* namevalue_conf  */
  YYSYMBOL_task_namevalue_conf = 28,       /* task_namevalue_conf  */
  YYSYMBOL_admin_namevalue_conf = 29,      /* admin_namevalue_conf  */
  YYSYMBOL_template_task_namevalue_conf = 30, /* template_task_namevalue_conf  */
  YYSYMBOL_template_admin_namevalue_conf = 31, /* template_admin_namevalue_conf  */
  YYSYMBOL_task_or_admin = 32,             /* task_or_admin  */
  YYSYMBOL_admin_conf = 33,                /* admin_conf  */
  YYSYMBOL_task_conf = 34,                 /* task_conf  */
  YYSYMBOL_template_admin_conf = 35,       /* template_admin_conf  */
  YYSYMBOL_template_task_conf = 36,        /* template_task_conf  */
  YYSYMBOL_mountvalue_conf = 37,           /* mountvalue_conf  */
  YYSYMBOL_mount = 38,                     /* mount  */
  YYSYMBOL_namespace_conf = 39,            /* namespace_conf  */
  YYSYMBOL_namespace = 40,                 /* namespace  */
  YYSYMBOL_systemdvalue_conf = 41,         /* systemdvalue_conf  */
  YYSYMBOL_systemd = 42                    /* systemd  */
};
typedef enum yysymbol_kind_t yysymbol_kind_t;




#ifdef short
# undef short
#endif

/* On compilers that do not define __PTRDIFF_MAX__ etc., make sure
   <limits.h> and (if available) <stdint.h> are included
   so that the code can choose integer types of a good width.  */

#ifndef __PTRDIFF_MAX__
# include <limits.h> /* INFRINGES ON USER NAME SPACE */
# if defined __STDC_VERSION__ && 199901 <= __STDC_VERSION__
#  include <stdint.h> /* INFRINGES ON USER NAME SPACE */
#  define YY_STDINT_H
# endif
#endif

/* Narrow types that promote to a signed type and that can represent a
   signed or unsigned integer of at least N bits.  In tables they can
   save space and decrease cache pressure.  Promoting to a signed type
   helps avoid bugs in integer arithmetic.  */

#ifdef __INT_LEAST8_MAX__
typedef __INT_LEAST8_TYPE__ yytype_int8;
#elif defined YY_STDINT_H
typedef int_least8_t yytype_int8;
#else
typedef signed char yytype_int8;
#endif

#ifdef __INT_LEAST16_MAX__
typedef __INT_LEAST16_TYPE__ yytype_int16;
#elif defined YY_STDINT_H
typedef int_least16_t yytype_int16;
#else
typedef short yytype_int16;
#endif

/* Work around bug in HP-UX 11.23, which defines these macros
   incorrectly for preprocessor constants.  This workaround can likely
   be removed in 2023, as HPE has promised support for HP-UX 11.23
   (aka HP-UX 11i v2) only through the end of 2022; see Table 2 of
   <https://h20195.www2.hpe.com/V2/getpdf.aspx/4AA4-7673ENW.pdf>.  */
#ifdef __hpux
# undef UINT_LEAST8_MAX
# undef UINT_LEAST16_MAX
# define UINT_LEAST8_MAX 255
# define UINT_LEAST16_MAX 65535
#endif

#if defined __UINT_LEAST8_MAX__ && __UINT_LEAST8_MAX__ <= __INT_MAX__
typedef __UINT_LEAST8_TYPE__ yytype_uint8;
#elif (!defined __UINT_LEAST8_MAX__ && defined YY_STDINT_H \
       && UINT_LEAST8_MAX <= INT_MAX)
typedef uint_least8_t yytype_uint8;
#elif !defined __UINT_LEAST8_MAX__ && UCHAR_MAX <= INT_MAX
typedef unsigned char yytype_uint8;
#else
typedef short yytype_uint8;
#endif

#if defined __UINT_LEAST16_MAX__ && __UINT_LEAST16_MAX__ <= __INT_MAX__
typedef __UINT_LEAST16_TYPE__ yytype_uint16;
#elif (!defined __UINT_LEAST16_MAX__ && defined YY_STDINT_H \
       && UINT_LEAST16_MAX <= INT_MAX)
typedef uint_least16_t yytype_uint16;
#elif !defined __UINT_LEAST16_MAX__ && USHRT_MAX <= INT_MAX
typedef unsigned short yytype_uint16;
#else
typedef int yytype_uint16;
#endif

#ifndef YYPTRDIFF_T
# if defined __PTRDIFF_TYPE__ && defined __PTRDIFF_MAX__
#  define YYPTRDIFF_T __PTRDIFF_TYPE__
#  define YYPTRDIFF_MAXIMUM __PTRDIFF_MAX__
# elif defined PTRDIFF_MAX
#  ifndef ptrdiff_t
#   include <stddef.h> /* INFRINGES ON USER NAME SPACE */
#  endif
#  define YYPTRDIFF_T ptrdiff_t
#  define YYPTRDIFF_MAXIMUM PTRDIFF_MAX
# else
#  define YYPTRDIFF_T long
#  define YYPTRDIFF_MAXIMUM LONG_MAX
# endif
#endif

#ifndef YYSIZE_T
# ifdef __SIZE_TYPE__
#  define YYSIZE_T __SIZE_TYPE__
# elif defined size_t
#  define YYSIZE_T size_t
# elif defined __STDC_VERSION__ && 199901 <= __STDC_VERSION__
#  include <stddef.h> /* INFRINGES ON USER NAME SPACE */
#  define YYSIZE_T size_t
# else
#  define YYSIZE_T unsigned
# endif
#endif

#define YYSIZE_MAXIMUM                                  \
  YY_CAST (YYPTRDIFF_T,                                 \
           (YYPTRDIFF_MAXIMUM < YY_CAST (YYSIZE_T, -1)  \
            ? YYPTRDIFF_MAXIMUM                         \
            : YY_CAST (YYSIZE_T, -1)))

#define YYSIZEOF(X) YY_CAST (YYPTRDIFF_T, sizeof (X))


/* Stored state numbers (used for stacks). */
typedef yytype_uint8 yy_state_t;

/* State numbers in computations.  */
typedef int yy_state_fast_t;

#ifndef YY_
# if defined YYENABLE_NLS && YYENABLE_NLS
#  if ENABLE_NLS
#   include <libintl.h> /* INFRINGES ON USER NAME SPACE */
#   define YY_(Msgid) dgettext ("bison-runtime", Msgid)
#  endif
# endif
# ifndef YY_
#  define YY_(Msgid) Msgid
# endif
#endif


#ifndef YY_ATTRIBUTE_PURE
# if defined __GNUC__ && 2 < __GNUC__ + (96 <= __GNUC_MINOR__)
#  define YY_ATTRIBUTE_PURE __attribute__ ((__pure__))
# else
#  define YY_ATTRIBUTE_PURE
# endif
#endif

#ifndef YY_ATTRIBUTE_UNUSED
# if defined __GNUC__ && 2 < __GNUC__ + (7 <= __GNUC_MINOR__)
#  define YY_ATTRIBUTE_UNUSED __attribute__ ((__unused__))
# else
#  define YY_ATTRIBUTE_UNUSED
# endif
#endif

/* Suppress unused-variable warnings by "using" E.  */
#if ! defined lint || defined __GNUC__
# define YY_USE(E) ((void) (E))
#else
# define YY_USE(E) /* empty */
#endif

/* Suppress an incorrect diagnostic about yylval being uninitialized.  */
#if defined __GNUC__ && ! defined __ICC && 406 <= __GNUC__ * 100 + __GNUC_MINOR__
# if __GNUC__ * 100 + __GNUC_MINOR__ < 407
#  define YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN                           \
    _Pragma ("GCC diagnostic push")                                     \
    _Pragma ("GCC diagnostic ignored \"-Wuninitialized\"")
# else
#  define YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN                           \
    _Pragma ("GCC diagnostic push")                                     \
    _Pragma ("GCC diagnostic ignored \"-Wuninitialized\"")              \
    _Pragma ("GCC diagnostic ignored \"-Wmaybe-uninitialized\"")
# endif
# define YY_IGNORE_MAYBE_UNINITIALIZED_END      \
    _Pragma ("GCC diagnostic pop")
#else
# define YY_INITIAL_VALUE(Value) Value
#endif
#ifndef YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN
# define YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN
# define YY_IGNORE_MAYBE_UNINITIALIZED_END
#endif
#ifndef YY_INITIAL_VALUE
# define YY_INITIAL_VALUE(Value) /* Nothing. */
#endif

#if defined __cplusplus && defined __GNUC__ && ! defined __ICC && 6 <= __GNUC__
# define YY_IGNORE_USELESS_CAST_BEGIN                          \
    _Pragma ("GCC diagnostic push")                            \
    _Pragma ("GCC diagnostic ignored \"-Wuseless-cast\"")
# define YY_IGNORE_USELESS_CAST_END            \
    _Pragma ("GCC diagnostic pop")
#endif
#ifndef YY_IGNORE_USELESS_CAST_BEGIN
# define YY_IGNORE_USELESS_CAST_BEGIN
# define YY_IGNORE_USELESS_CAST_END
#endif


#define YY_ASSERT(E) ((void) (0 && (E)))

#if !defined yyoverflow

/* The parser invokes alloca or malloc; define the necessary symbols.  */

# ifdef YYSTACK_USE_ALLOCA
#  if YYSTACK_USE_ALLOCA
#   ifdef __GNUC__
#    define YYSTACK_ALLOC __builtin_alloca
#   elif defined __BUILTIN_VA_ARG_INCR
#    include <alloca.h> /* INFRINGES ON USER NAME SPACE */
#   elif defined _AIX
#    define YYSTACK_ALLOC __alloca
#   elif defined _MSC_VER
#    include <malloc.h> /* INFRINGES ON USER NAME SPACE */
#    define alloca _alloca
#   else
#    define YYSTACK_ALLOC alloca
#    if ! defined _ALLOCA_H && ! defined EXIT_SUCCESS
#     include <stdlib.h> /* INFRINGES ON USER NAME SPACE */
      /* Use EXIT_SUCCESS as a witness for stdlib.h.  */
#     ifndef EXIT_SUCCESS
#      define EXIT_SUCCESS 0
#     endif
#    endif
#   endif
#  endif
# endif

# ifdef YYSTACK_ALLOC
   /* Pacify GCC's 'empty if-body' warning.  */
#  define YYSTACK_FREE(Ptr) do { /* empty */; } while (0)
#  ifndef YYSTACK_ALLOC_MAXIMUM
    /* The OS might guarantee only one guard page at the bottom of the stack,
       and a page size can be as small as 4096 bytes.  So we cannot safely
       invoke alloca (N) if N exceeds 4096.  Use a slightly smaller number
       to allow for a few compiler-allocated temporary stack slots.  */
#   define YYSTACK_ALLOC_MAXIMUM 4032 /* reasonable circa 2006 */
#  endif
# else
#  define YYSTACK_ALLOC YYMALLOC
#  define YYSTACK_FREE YYFREE
#  ifndef YYSTACK_ALLOC_MAXIMUM
#   define YYSTACK_ALLOC_MAXIMUM YYSIZE_MAXIMUM
#  endif
#  if (defined __cplusplus && ! defined EXIT_SUCCESS \
       && ! ((defined YYMALLOC || defined malloc) \
             && (defined YYFREE || defined free)))
#   include <stdlib.h> /* INFRINGES ON USER NAME SPACE */
#   ifndef EXIT_SUCCESS
#    define EXIT_SUCCESS 0
#   endif
#  endif
#  ifndef YYMALLOC
#   define YYMALLOC malloc
#   if ! defined malloc && ! defined EXIT_SUCCESS
void *malloc (YYSIZE_T); /* INFRINGES ON USER NAME SPACE */
#   endif
#  endif
#  ifndef YYFREE
#   define YYFREE free
#   if ! defined free && ! defined EXIT_SUCCESS
void free (void *); /* INFRINGES ON USER NAME SPACE */
#   endif
#  endif
# endif
#endif /* !defined yyoverflow */

#if (! defined yyoverflow \
     && (! defined __cplusplus \
         || (defined YYSTYPE_IS_TRIVIAL && YYSTYPE_IS_TRIVIAL)))

/* A type that is properly aligned for any stack member.  */
union yyalloc
{
  yy_state_t yyss_alloc;
  YYSTYPE yyvs_alloc;
};

/* The size of the maximum gap between one aligned stack and the next.  */
# define YYSTACK_GAP_MAXIMUM (YYSIZEOF (union yyalloc) - 1)

/* The size of an array large to enough to hold all stacks, each with
   N elements.  */
# define YYSTACK_BYTES(N) \
     ((N) * (YYSIZEOF (yy_state_t) + YYSIZEOF (YYSTYPE)) \
      + YYSTACK_GAP_MAXIMUM)

# define YYCOPY_NEEDED 1

/* Relocate STACK from its old location to the new one.  The
   local variables YYSIZE and YYSTACKSIZE give the old and new number of
   elements in the stack, and YYPTR gives the new location of the
   stack.  Advance YYPTR to a properly aligned location for the next
   stack.  */
# define YYSTACK_RELOCATE(Stack_alloc, Stack)                           \
    do                                                                  \
      {                                                                 \
        YYPTRDIFF_T yynewbytes;                                         \
        YYCOPY (&yyptr->Stack_alloc, Stack, yysize);                    \
        Stack = &yyptr->Stack_alloc;                                    \
        yynewbytes = yystacksize * YYSIZEOF (*Stack) + YYSTACK_GAP_MAXIMUM; \
        yyptr += yynewbytes / YYSIZEOF (*yyptr);                        \
      }                                                                 \
    while (0)

#endif

#if defined YYCOPY_NEEDED && YYCOPY_NEEDED
/* Copy COUNT objects from SRC to DST.  The source and destination do
   not overlap.  */
# ifndef YYCOPY
#  if defined __GNUC__ && 1 < __GNUC__
#   define YYCOPY(Dst, Src, Count) \
      __builtin_memcpy (Dst, Src, YY_CAST (YYSIZE_T, (Count)) * sizeof (*(Src)))
#  else
#   define YYCOPY(Dst, Src, Count)              \
      do                                        \
        {                                       \
          YYPTRDIFF_T yyi;                      \
          for (yyi = 0; yyi < (Count); yyi++)   \
            (Dst)[yyi] = (Src)[yyi];            \
        }                                       \
      while (0)
#  endif
# endif
#endif /* !YYCOPY_NEEDED */

/* YYFINAL -- State number of the termination state.  */
#define YYFINAL  2
/* YYLAST -- Last index in YYTABLE.  */
#define YYLAST   151

/* YYNTOKENS -- Number of terminals.  */
#define YYNTOKENS  17
/* YYNNTS -- Number of nonterminals.  */
#define YYNNTS  26
/* YYNRULES -- Number of rules.  */
#define YYNRULES  48
/* YYNSTATES -- Number of states.  */
#define YYNSTATES  165

/* YYMAXUTOK -- Last valid token kind.  */
#define YYMAXUTOK   267


/* YYTRANSLATE(TOKEN-NUM) -- Symbol number corresponding to TOKEN-NUM
   as returned by yylex, with out-of-bounds checking.  */
#define YYTRANSLATE(YYX)                                \
  (0 <= (YYX) && (YYX) <= YYMAXUTOK                     \
   ? YY_CAST (yysymbol_kind_t, yytranslate[YYX])        \
   : YYSYMBOL_YYUNDEF)

/* YYTRANSLATE[TOKEN-NUM] -- Symbol number corresponding to TOKEN-NUM
   as returned by yylex.  */
static const yytype_int8 yytranslate[] =
{
       0,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,    16,
       2,    15,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,    13,     2,    14,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     2,     2,     2,     2,
       2,     2,     2,     2,     2,     2,     1,     2,     3,     4,
       5,     6,     7,     8,     9,    10,    11,    12
};

#if YYDEBUG
/* YYRLINE[YYN] -- Source line where rule number YYN was defined.  */
static const yytype_int16 yyrline[] =
{
       0,    55,    55,    59,    63,    67,    71,    75,    80,    85,
      95,   101,   120,   124,   130,   140,   150,   161,   181,   191,
     201,   213,   222,   235,   251,   264,   270,   279,   291,   300,
     312,   321,   333,   342,   355,   364,   375,   386,   397,   408,
     420,   429,   440,   452,   461,   472,   484,   493,   504
};
#endif

/** Accessing symbol of state STATE.  */
#define YY_ACCESSING_SYMBOL(State) YY_CAST (yysymbol_kind_t, yystos[State])

#if YYDEBUG || 0
/* The user-facing name of the symbol whose (internal) number is
   YYSYMBOL.  No bounds checking.  */
static const char *yysymbol_name (yysymbol_kind_t yysymbol) YY_ATTRIBUTE_UNUSED;

/* YYTNAME[SYMBOL-NUM] -- String name of the symbol SYMBOL-NUM.
   First, the terminals, then, starting at YYNTOKENS, nonterminals.  */
static const char *const yytname[] =
{
  "\"end of file\"", "error", "\"invalid token\"", "ID", "MOUNT", "GROUP",
  "PERM", "TASK", "ADMIN", "NAMESPACE", "DEFAULT", "TEMPLATE", "SYSTEMD",
  "'{'", "'}'", "'='", "';'", "$accept", "start", "default",
  "default_conf", "group", "group_name", "group_conf", "template",
  "template_conf", "template_task_or_admin", "namevalue_conf",
  "task_namevalue_conf", "admin_namevalue_conf",
  "template_task_namevalue_conf", "template_admin_namevalue_conf",
  "task_or_admin", "admin_conf", "task_conf", "template_admin_conf",
  "template_task_conf", "mountvalue_conf", "mount", "namespace_conf",
  "namespace", "systemdvalue_conf", "systemd", YY_NULLPTR
};

static const char *
yysymbol_name (yysymbol_kind_t yysymbol)
{
  return yytname[yysymbol];
}
#endif

#define YYPACT_NINF (-62)

#define yypact_value_is_default(Yyn) \
  ((Yyn) == YYPACT_NINF)

#define YYTABLE_NINF (-1)

#define yytable_value_is_error(Yyn) \
  0

/* YYPACT[STATE-NUM] -- Index in YYTABLE of the portion describing
   STATE-NUM.  */
static const yytype_int16 yypact[] =
{
     -62,     3,   -62,   -11,    43,    -7,    -3,    44,    47,   -62,
     -62,   -62,   -62,   -62,   -62,    46,   -62,   -62,    48,    59,
      57,    52,    61,    39,     2,    -2,    53,     6,    54,    55,
      42,    56,    14,    63,    58,   -62,    62,    64,    15,    67,
      65,   -62,    49,   -62,    66,    68,    16,    69,    70,   -62,
      60,    71,    75,    49,    73,   -62,    72,    79,    74,    76,
      77,    75,    51,    80,   -62,    78,    81,   -62,    82,    84,
      19,    83,    75,   -62,    85,    87,    89,   -62,    20,    90,
      91,    86,    75,   -62,    92,   -62,    93,    94,   -62,   -62,
      21,   -62,    95,    22,    96,    23,   -62,    99,   102,   -62,
      24,   -62,    97,   103,   -62,   104,   100,   106,   109,   101,
      88,   105,    28,   107,    29,   -62,   -62,   108,   110,   114,
     112,   -62,   111,   115,   116,   -62,   118,   113,   122,   120,
     117,   124,   -62,   -62,   119,    89,   -62,   121,    87,   123,
     130,   125,   -62,   126,   131,   127,   -62,   -62,    36,   -62,
      37,   -62,   128,   102,   -62,   129,    99,   -62,   -62,   -62,
      38,   -62,    41,   -62,   -62
};

/* YYDEFACT[STATE-NUM] -- Default reduction number in state STATE-NUM.
   Performed when YYTABLE does not specify something else to do.  Zero
   means the default is an error.  */
static const yytype_int8 yydefact[] =
{
       8,     0,     1,     0,     0,     0,     0,     0,     0,     4,
       2,     6,     3,     5,     7,     0,    12,    13,     0,     0,
       0,     0,     0,     0,     0,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,    42,     0,     0,     0,     0,
       0,    45,     0,     9,     0,     0,     0,     0,     0,    48,
       0,     0,    25,     0,     0,    11,     0,     0,     0,     0,
       0,    25,     0,     0,    17,     0,     0,    40,     0,     0,
       0,     0,    25,    43,     0,     0,     0,    10,     0,     0,
       0,     0,    25,    46,     0,    41,     0,     0,    14,    16,
       0,    44,     0,     0,     0,     0,    18,     0,     0,    20,
       0,    47,     0,     0,    15,     0,     0,     0,     0,     0,
       0,     0,     0,     0,     0,    19,    23,     0,     0,     0,
       0,    34,     0,     0,     0,    35,     0,     0,     0,     0,
       0,     0,    24,    26,     0,     0,    28,     0,     0,     0,
       0,     0,    21,     0,     0,     0,    22,    27,     0,    29,
       0,    30,     0,     0,    32,     0,     0,    36,    37,    31,
       0,    33,     0,    38,    39
};

/* YYPGOTO[NTERM-NUM].  */
static const yytype_int8 yypgoto[] =
{
     -62,   -62,   -62,   -62,   -62,   -62,   -62,   -62,   -62,   -62,
     -61,   -55,   -16,   -20,   -12,    98,   -62,   -62,   -62,   -62,
     -62,   -62,   -62,   -62,   -62,   -62
};

/* YYDEFGOTO[NTERM-NUM].  */
static const yytype_uint8 yydefgoto[] =
{
       0,     1,     9,    29,    10,    18,    38,    11,    46,    81,
      70,    93,    95,   112,   114,    60,   121,   125,   142,   146,
      24,    12,    27,    13,    32,    14
};

/* YYTABLE[YYPACT[STATE-NUM]] -- What to do in state STATE-NUM.  If
   positive, shift that token.  If negative, reduce the rule whose
   number is the opposite.  If YYTABLE_NINF, syntax error.  */
static const yytype_uint8 yytable[] =
{
      78,    36,    15,     2,    37,    34,    19,     3,     4,    40,
      20,    90,     5,     6,     7,     8,    35,    48,    54,    63,
      41,   100,    87,    87,    87,   106,   109,    87,    49,    55,
      64,   127,   130,    88,    96,   104,   107,   110,   115,   109,
     106,   130,   128,   131,   127,    44,    16,    21,    45,    23,
     157,   158,   163,    17,    33,   164,    58,    59,    79,    80,
      22,    25,    26,    28,    31,    30,    50,    42,    39,    43,
      56,    47,    65,    51,    68,    52,    67,    53,    69,    61,
      57,    62,    74,   150,    84,    66,    72,    75,    73,    76,
      92,    77,    94,    82,    83,   124,   102,    89,    85,    86,
      99,    91,   111,    97,    98,   113,   117,   118,   101,   103,
     105,   108,   122,   116,   120,   119,   123,   134,   137,   148,
     126,   139,   129,   143,   132,   135,   133,   136,   140,   138,
     141,   145,   144,   152,   155,   147,   162,   149,   153,   151,
     156,   160,   154,     0,   159,   161,     0,     0,     0,     0,
       0,    71
};

static const yytype_int16 yycheck[] =
{
      61,     3,    13,     0,     6,     3,    13,     4,     5,     3,
      13,    72,     9,    10,    11,    12,    14,     3,     3,     3,
      14,    82,     3,     3,     3,     3,     3,     3,    14,    14,
      14,     3,     3,    14,    14,    14,    14,    14,    14,     3,
       3,     3,    14,    14,     3,     3,     3,     3,     6,     3,
      14,    14,    14,    10,    15,    14,     7,     8,     7,     8,
      13,    13,     3,     6,     3,    13,     3,    13,    15,    14,
       3,    15,     3,    15,     3,    13,    16,    13,     3,    13,
      15,    13,     3,   138,     3,    15,    13,    13,    16,    13,
       3,    14,     3,    13,    16,     7,     3,    14,    16,    15,
      14,    16,     3,    13,    13,     3,     3,     3,    16,    15,
      15,    15,     3,    16,     8,    15,    15,     3,     3,   135,
      15,     3,    15,     3,    16,    13,    16,    16,    15,    13,
       8,     7,    15,     3,     3,    16,   156,    16,    13,    16,
      13,   153,    16,    -1,    16,    16,    -1,    -1,    -1,    -1,
      -1,    53
};

/* YYSTOS[STATE-NUM] -- The symbol kind of the accessing symbol of
   state STATE-NUM.  */
static const yytype_int8 yystos[] =
{
       0,    18,     0,     4,     5,     9,    10,    11,    12,    19,
      21,    24,    38,    40,    42,    13,     3,    10,    22,    13,
      13,     3,    13,     3,    37,    13,     3,    39,     6,    20,
      13,     3,    41,    15,     3,    14,     3,     6,    23,    15,
       3,    14,    13,    14,     3,     6,    25,    15,     3,    14,
       3,    15,    13,    13,     3,    14,     3,    15,     7,     8,
      32,    13,    13,     3,    14,     3,    15,    16,     3,     3,
      27,    32,    13,    16,     3,    13,    13,    14,    27,     7,
       8,    26,    13,    16,     3,    16,    15,     3,    14,    14,
      27,    16,     3,    28,     3,    29,    14,    13,    13,    14,
      27,    16,     3,    15,    14,    15,     3,    14,    15,     3,
      14,     3,    30,     3,    31,    14,    16,     3,     3,    15,
       8,    33,     3,    15,     7,    34,    15,     3,    14,    15,
       3,    14,    16,    16,     3,    13,    16,     3,    13,     3,
      15,     8,    35,     3,    15,     7,    36,    16,    29,    16,
      28,    16,     3,    13,    16,     3,    13,    14,    14,    16,
      31,    16,    30,    14,    14
};

/* YYR1[RULE-NUM] -- Symbol kind of the left-hand side of rule RULE-NUM.  */
static const yytype_int8 yyr1[] =
{
       0,    17,    18,    18,    18,    18,    18,    18,    18,    19,
      20,    21,    22,    22,    23,    23,    23,    24,    25,    25,
      25,    26,    26,    27,    27,    27,    28,    28,    29,    29,
      30,    30,    31,    31,    32,    32,    33,    34,    35,    36,
      37,    37,    38,    39,    39,    40,    41,    41,    42
};

/* YYR2[RULE-NUM] -- Number of symbols on the right-hand side of rule RULE-NUM.  */
static const yytype_int8 yyr2[] =
{
       0,     2,     2,     2,     2,     2,     2,     2,     0,     4,
       4,     5,     1,     1,     4,     5,     4,     5,     4,     5,
       4,     5,     5,     4,     5,     0,     4,     5,     4,     5,
       4,     5,     4,     5,     5,     5,     4,     4,     4,     4,
       4,     5,     4,     4,     5,     4,     4,     5,     4
};


enum { YYENOMEM = -2 };

#define yyerrok         (yyerrstatus = 0)
#define yyclearin       (yychar = YYEMPTY)

#define YYACCEPT        goto yyacceptlab
#define YYABORT         goto yyabortlab
#define YYERROR         goto yyerrorlab
#define YYNOMEM         goto yyexhaustedlab


#define YYRECOVERING()  (!!yyerrstatus)

#define YYBACKUP(Token, Value)                                    \
  do                                                              \
    if (yychar == YYEMPTY)                                        \
      {                                                           \
        yychar = (Token);                                         \
        yylval = (Value);                                         \
        YYPOPSTACK (yylen);                                       \
        yystate = *yyssp;                                         \
        goto yybackup;                                            \
      }                                                           \
    else                                                          \
      {                                                           \
        yyerror (YY_("syntax error: cannot back up")); \
        YYERROR;                                                  \
      }                                                           \
  while (0)

/* Backward compatibility with an undocumented macro.
   Use YYerror or YYUNDEF. */
#define YYERRCODE YYUNDEF


/* Enable debugging if requested.  */
#if YYDEBUG

# ifndef YYFPRINTF
#  include <stdio.h> /* INFRINGES ON USER NAME SPACE */
#  define YYFPRINTF fprintf
# endif

# define YYDPRINTF(Args)                        \
do {                                            \
  if (yydebug)                                  \
    YYFPRINTF Args;                             \
} while (0)




# define YY_SYMBOL_PRINT(Title, Kind, Value, Location)                    \
do {                                                                      \
  if (yydebug)                                                            \
    {                                                                     \
      YYFPRINTF (stderr, "%s ", Title);                                   \
      yy_symbol_print (stderr,                                            \
                  Kind, Value); \
      YYFPRINTF (stderr, "\n");                                           \
    }                                                                     \
} while (0)


/*-----------------------------------.
| Print this symbol's value on YYO.  |
`-----------------------------------*/

static void
yy_symbol_value_print (FILE *yyo,
                       yysymbol_kind_t yykind, YYSTYPE const * const yyvaluep)
{
  FILE *yyoutput = yyo;
  YY_USE (yyoutput);
  if (!yyvaluep)
    return;
  YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN
  YY_USE (yykind);
  YY_IGNORE_MAYBE_UNINITIALIZED_END
}


/*---------------------------.
| Print this symbol on YYO.  |
`---------------------------*/

static void
yy_symbol_print (FILE *yyo,
                 yysymbol_kind_t yykind, YYSTYPE const * const yyvaluep)
{
  YYFPRINTF (yyo, "%s %s (",
             yykind < YYNTOKENS ? "token" : "nterm", yysymbol_name (yykind));

  yy_symbol_value_print (yyo, yykind, yyvaluep);
  YYFPRINTF (yyo, ")");
}

/*------------------------------------------------------------------.
| yy_stack_print -- Print the state stack from its BOTTOM up to its |
| TOP (included).                                                   |
`------------------------------------------------------------------*/

static void
yy_stack_print (yy_state_t *yybottom, yy_state_t *yytop)
{
  YYFPRINTF (stderr, "Stack now");
  for (; yybottom <= yytop; yybottom++)
    {
      int yybot = *yybottom;
      YYFPRINTF (stderr, " %d", yybot);
    }
  YYFPRINTF (stderr, "\n");
}

# define YY_STACK_PRINT(Bottom, Top)                            \
do {                                                            \
  if (yydebug)                                                  \
    yy_stack_print ((Bottom), (Top));                           \
} while (0)


/*------------------------------------------------.
| Report that the YYRULE is going to be reduced.  |
`------------------------------------------------*/

static void
yy_reduce_print (yy_state_t *yyssp, YYSTYPE *yyvsp,
                 int yyrule)
{
  int yylno = yyrline[yyrule];
  int yynrhs = yyr2[yyrule];
  int yyi;
  YYFPRINTF (stderr, "Reducing stack by rule %d (line %d):\n",
             yyrule - 1, yylno);
  /* The symbols being reduced.  */
  for (yyi = 0; yyi < yynrhs; yyi++)
    {
      YYFPRINTF (stderr, "   $%d = ", yyi + 1);
      yy_symbol_print (stderr,
                       YY_ACCESSING_SYMBOL (+yyssp[yyi + 1 - yynrhs]),
                       &yyvsp[(yyi + 1) - (yynrhs)]);
      YYFPRINTF (stderr, "\n");
    }
}

# define YY_REDUCE_PRINT(Rule)          \
do {                                    \
  if (yydebug)                          \
    yy_reduce_print (yyssp, yyvsp, Rule); \
} while (0)

/* Nonzero means print parse trace.  It is left uninitialized so that
   multiple parsers can coexist.  */
int yydebug;
#else /* !YYDEBUG */
# define YYDPRINTF(Args) ((void) 0)
# define YY_SYMBOL_PRINT(Title, Kind, Value, Location)
# define YY_STACK_PRINT(Bottom, Top)
# define YY_REDUCE_PRINT(Rule)
#endif /* !YYDEBUG */


/* YYINITDEPTH -- initial size of the parser's stacks.  */
#ifndef YYINITDEPTH
# define YYINITDEPTH 200
#endif

/* YYMAXDEPTH -- maximum size the stacks can grow to (effective only
   if the built-in stack extension method is used).

   Do not make this value too large; the results are undefined if
   YYSTACK_ALLOC_MAXIMUM < YYSTACK_BYTES (YYMAXDEPTH)
   evaluated with infinite-precision integer arithmetic.  */

#ifndef YYMAXDEPTH
# define YYMAXDEPTH 10000
#endif






/*-----------------------------------------------.
| Release the memory associated to this symbol.  |
`-----------------------------------------------*/

static void
yydestruct (const char *yymsg,
            yysymbol_kind_t yykind, YYSTYPE *yyvaluep)
{
  YY_USE (yyvaluep);
  if (!yymsg)
    yymsg = "Deleting";
  YY_SYMBOL_PRINT (yymsg, yykind, yyvaluep, yylocationp);

  YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN
  YY_USE (yykind);
  YY_IGNORE_MAYBE_UNINITIALIZED_END
}


/* Lookahead token kind.  */
int yychar;

/* The semantic value of the lookahead symbol.  */
YYSTYPE yylval;
/* Number of syntax errors so far.  */
int yynerrs;




/*----------.
| yyparse.  |
`----------*/

int
yyparse (void)
{
    yy_state_fast_t yystate = 0;
    /* Number of tokens to shift before error messages enabled.  */
    int yyerrstatus = 0;

    /* Refer to the stacks through separate pointers, to allow yyoverflow
       to reallocate them elsewhere.  */

    /* Their size.  */
    YYPTRDIFF_T yystacksize = YYINITDEPTH;

    /* The state stack: array, bottom, top.  */
    yy_state_t yyssa[YYINITDEPTH];
    yy_state_t *yyss = yyssa;
    yy_state_t *yyssp = yyss;

    /* The semantic value stack: array, bottom, top.  */
    YYSTYPE yyvsa[YYINITDEPTH];
    YYSTYPE *yyvs = yyvsa;
    YYSTYPE *yyvsp = yyvs;

  int yyn;
  /* The return value of yyparse.  */
  int yyresult;
  /* Lookahead symbol kind.  */
  yysymbol_kind_t yytoken = YYSYMBOL_YYEMPTY;
  /* The variables used to return semantic value and location from the
     action routines.  */
  YYSTYPE yyval;



#define YYPOPSTACK(N)   (yyvsp -= (N), yyssp -= (N))

  /* The number of symbols on the RHS of the reduced rule.
     Keep to zero when no symbol should be popped.  */
  int yylen = 0;

  YYDPRINTF ((stderr, "Starting parse\n"));

  yychar = YYEMPTY; /* Cause a token to be read.  */

  goto yysetstate;


/*------------------------------------------------------------.
| yynewstate -- push a new state, which is found in yystate.  |
`------------------------------------------------------------*/
yynewstate:
  /* In all cases, when you get here, the value and location stacks
     have just been pushed.  So pushing a state here evens the stacks.  */
  yyssp++;


/*--------------------------------------------------------------------.
| yysetstate -- set current state (the top of the stack) to yystate.  |
`--------------------------------------------------------------------*/
yysetstate:
  YYDPRINTF ((stderr, "Entering state %d\n", yystate));
  YY_ASSERT (0 <= yystate && yystate < YYNSTATES);
  YY_IGNORE_USELESS_CAST_BEGIN
  *yyssp = YY_CAST (yy_state_t, yystate);
  YY_IGNORE_USELESS_CAST_END
  YY_STACK_PRINT (yyss, yyssp);

  if (yyss + yystacksize - 1 <= yyssp)
#if !defined yyoverflow && !defined YYSTACK_RELOCATE
    YYNOMEM;
#else
    {
      /* Get the current used size of the three stacks, in elements.  */
      YYPTRDIFF_T yysize = yyssp - yyss + 1;

# if defined yyoverflow
      {
        /* Give user a chance to reallocate the stack.  Use copies of
           these so that the &'s don't force the real ones into
           memory.  */
        yy_state_t *yyss1 = yyss;
        YYSTYPE *yyvs1 = yyvs;

        /* Each stack pointer address is followed by the size of the
           data in use in that stack, in bytes.  This used to be a
           conditional around just the two extra args, but that might
           be undefined if yyoverflow is a macro.  */
        yyoverflow (YY_("memory exhausted"),
                    &yyss1, yysize * YYSIZEOF (*yyssp),
                    &yyvs1, yysize * YYSIZEOF (*yyvsp),
                    &yystacksize);
        yyss = yyss1;
        yyvs = yyvs1;
      }
# else /* defined YYSTACK_RELOCATE */
      /* Extend the stack our own way.  */
      if (YYMAXDEPTH <= yystacksize)
        YYNOMEM;
      yystacksize *= 2;
      if (YYMAXDEPTH < yystacksize)
        yystacksize = YYMAXDEPTH;

      {
        yy_state_t *yyss1 = yyss;
        union yyalloc *yyptr =
          YY_CAST (union yyalloc *,
                   YYSTACK_ALLOC (YY_CAST (YYSIZE_T, YYSTACK_BYTES (yystacksize))));
        if (! yyptr)
          YYNOMEM;
        YYSTACK_RELOCATE (yyss_alloc, yyss);
        YYSTACK_RELOCATE (yyvs_alloc, yyvs);
#  undef YYSTACK_RELOCATE
        if (yyss1 != yyssa)
          YYSTACK_FREE (yyss1);
      }
# endif

      yyssp = yyss + yysize - 1;
      yyvsp = yyvs + yysize - 1;

      YY_IGNORE_USELESS_CAST_BEGIN
      YYDPRINTF ((stderr, "Stack size increased to %ld\n",
                  YY_CAST (long, yystacksize)));
      YY_IGNORE_USELESS_CAST_END

      if (yyss + yystacksize - 1 <= yyssp)
        YYABORT;
    }
#endif /* !defined yyoverflow && !defined YYSTACK_RELOCATE */


  if (yystate == YYFINAL)
    YYACCEPT;

  goto yybackup;


/*-----------.
| yybackup.  |
`-----------*/
yybackup:
  /* Do appropriate processing given the current state.  Read a
     lookahead token if we need one and don't already have one.  */

  /* First try to decide what to do without reference to lookahead token.  */
  yyn = yypact[yystate];
  if (yypact_value_is_default (yyn))
    goto yydefault;

  /* Not known => get a lookahead token if don't already have one.  */

  /* YYCHAR is either empty, or end-of-input, or a valid lookahead.  */
  if (yychar == YYEMPTY)
    {
      YYDPRINTF ((stderr, "Reading a token\n"));
      yychar = yylex ();
    }

  if (yychar <= YYEOF)
    {
      yychar = YYEOF;
      yytoken = YYSYMBOL_YYEOF;
      YYDPRINTF ((stderr, "Now at end of input.\n"));
    }
  else if (yychar == YYerror)
    {
      /* The scanner already issued an error message, process directly
         to error recovery.  But do not keep the error token as
         lookahead, it is too special and may lead us to an endless
         loop in error recovery. */
      yychar = YYUNDEF;
      yytoken = YYSYMBOL_YYerror;
      goto yyerrlab1;
    }
  else
    {
      yytoken = YYTRANSLATE (yychar);
      YY_SYMBOL_PRINT ("Next token is", yytoken, &yylval, &yylloc);
    }

  /* If the proper action on seeing token YYTOKEN is to reduce or to
     detect an error, take that action.  */
  yyn += yytoken;
  if (yyn < 0 || YYLAST < yyn || yycheck[yyn] != yytoken)
    goto yydefault;
  yyn = yytable[yyn];
  if (yyn <= 0)
    {
      if (yytable_value_is_error (yyn))
        goto yyerrlab;
      yyn = -yyn;
      goto yyreduce;
    }

  /* Count tokens shifted since error; after three, turn off error
     status.  */
  if (yyerrstatus)
    yyerrstatus--;

  /* Shift the lookahead token.  */
  YY_SYMBOL_PRINT ("Shifting", yytoken, &yylval, &yylloc);
  yystate = yyn;
  YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN
  *++yyvsp = yylval;
  YY_IGNORE_MAYBE_UNINITIALIZED_END

  /* Discard the shifted token.  */
  yychar = YYEMPTY;
  goto yynewstate;


/*-----------------------------------------------------------.
| yydefault -- do the default action for the current state.  |
`-----------------------------------------------------------*/
yydefault:
  yyn = yydefact[yystate];
  if (yyn == 0)
    goto yyerrlab;
  goto yyreduce;


/*-----------------------------.
| yyreduce -- do a reduction.  |
`-----------------------------*/
yyreduce:
  /* yyn is the number of a rule to reduce with.  */
  yylen = yyr2[yyn];

  /* If YYLEN is nonzero, implement the default value of the action:
     '$$ = $1'.

     Otherwise, the following line sets YYVAL to garbage.
     This behavior is undocumented and Bison
     users should not rely upon it.  Assigning to YYVAL
     unconditionally makes the parser a bit smaller, and it avoids a
     GCC warning that YYVAL may be used uninitialized.  */
  yyval = yyvsp[1-yylen];


  YY_REDUCE_PRINT (yyn);
  switch (yyn)
    {
  case 2: /* start: start group  */
#line 56 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
	}
#line 1290 "parse.c"
    break;

  case 3: /* start: start mount  */
#line 60 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
	}
#line 1298 "parse.c"
    break;

  case 4: /* start: start default  */
#line 64 "parse.y"
        {
	  (yyval.val) = (yyvsp[-1].val);
	}
#line 1306 "parse.c"
    break;

  case 5: /* start: start namespace  */
#line 68 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
	}
#line 1314 "parse.c"
    break;

  case 6: /* start: start template  */
#line 72 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
	}
#line 1322 "parse.c"
    break;

  case 7: /* start: start systemd  */
#line 76 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
	}
#line 1330 "parse.c"
    break;

  case 8: /* start: %empty  */
#line 80 "parse.y"
        {
		(yyval.val) = 1;
	}
#line 1338 "parse.c"
    break;

  case 9: /* default: DEFAULT '{' default_conf '}'  */
#line 86 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if ((yyval.val)) {
			cgroup_config_define_default();
		}
	}
#line 1349 "parse.c"
    break;

  case 10: /* default_conf: PERM '{' task_or_admin '}'  */
#line 96 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
	}
#line 1357 "parse.c"
    break;

  case 11: /* group: GROUP group_name '{' group_conf '}'  */
#line 102 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if ((yyval.val)) {
			(yyval.val) = cgroup_config_insert_cgroup((yyvsp[-3].name));
			if (!(yyval.val)) {
				fprintf(stderr, "failed to insert group check size and memory");
				(yyval.val) = ECGOTHER;
				return (yyval.val);
			}
		} else {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1377 "parse.c"
    break;

  case 12: /* group_name: ID  */
#line 121 "parse.y"
        {
		(yyval.name) = (yyvsp[0].name);
	}
#line 1385 "parse.c"
    break;

  case 13: /* group_name: DEFAULT  */
#line 125 "parse.y"
        {
		(yyval.name) = (yyvsp[0].name);
	}
#line 1393 "parse.c"
    break;

  case 14: /* group_conf: ID '{' namevalue_conf '}'  */
#line 131 "parse.y"
        {
		(yyval.val) = cgroup_config_parse_controller_options((yyvsp[-3].name), (yyvsp[-1].values));
		cgroup_dictionary_free((yyvsp[-1].values));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1407 "parse.c"
    break;

  case 15: /* group_conf: group_conf ID '{' namevalue_conf '}'  */
#line 141 "parse.y"
        {
		(yyval.val) = cgroup_config_parse_controller_options((yyvsp[-3].name), (yyvsp[-1].values));
		cgroup_dictionary_free((yyvsp[-1].values));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1421 "parse.c"
    break;

  case 16: /* group_conf: PERM '{' task_or_admin '}'  */
#line 151 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1434 "parse.c"
    break;

  case 17: /* template: TEMPLATE ID '{' template_conf '}'  */
#line 162 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if ((yyval.val)) {
			(yyval.val) = template_config_insert_cgroup((yyvsp[-3].name));
			if (!(yyval.val)) {
				fprintf(stderr, "parsing failed at line number %d\n", line_no);
				(yyval.val) = ECGOTHER;
				return (yyval.val);
			}
		} else {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1454 "parse.c"
    break;

  case 18: /* template_conf: ID '{' namevalue_conf '}'  */
#line 182 "parse.y"
        {
		(yyval.val) = template_config_parse_controller_options((yyvsp[-3].name), (yyvsp[-1].values));
		cgroup_dictionary_free((yyvsp[-1].values));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1468 "parse.c"
    break;

  case 19: /* template_conf: template_conf ID '{' namevalue_conf '}'  */
#line 192 "parse.y"
        {
		(yyval.val) = template_config_parse_controller_options((yyvsp[-3].name), (yyvsp[-1].values));
		cgroup_dictionary_free((yyvsp[-1].values));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1482 "parse.c"
    break;

  case 20: /* template_conf: PERM '{' template_task_or_admin '}'  */
#line 202 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1495 "parse.c"
    break;

  case 21: /* template_task_or_admin: TASK '{' template_task_namevalue_conf '}' template_admin_conf  */
#line 214 "parse.y"
        {
	(yyval.val) = (yyvsp[-2].val) && (yyvsp[0].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1508 "parse.c"
    break;

  case 22: /* template_task_or_admin: ADMIN '{' template_admin_namevalue_conf '}' template_task_conf  */
#line 223 "parse.y"
        {
		(yyval.val) = (yyvsp[-2].val) && (yyvsp[0].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
		return (yyval.val);
		}
	}
#line 1521 "parse.c"
    break;

  case 23: /* namevalue_conf: ID '=' ID ';'  */
#line 236 "parse.y"
        {
		struct cgroup_dictionary *dict;
		int ret;
		ret = cgroup_dictionary_create(&dict, 0);
		if (ret == 0)
			ret = cgroup_dictionary_add(dict, (yyvsp[-3].name), (yyvsp[-1].name));
		if (ret) {
			fprintf(stderr, "parsing failed at line number %d:%s\n", line_no,
				cgroup_strerror(ret));
			(yyval.values) = NULL;
			cgroup_dictionary_free(dict);
			return ECGCONFIGPARSEFAIL;
		}
		(yyval.values) = dict;
	}
#line 1541 "parse.c"
    break;

  case 24: /* namevalue_conf: namevalue_conf ID '=' ID ';'  */
#line 252 "parse.y"
        {
		int ret = 0;
		ret = cgroup_dictionary_add((yyvsp[-4].values), (yyvsp[-3].name), (yyvsp[-1].name));
		if (ret != 0) {
			fprintf(stderr, "parsing failed at line number %d: %s\n", line_no,
				cgroup_strerror(ret));
			(yyval.values) = NULL;
			return ECGCONFIGPARSEFAIL;
		}
		(yyval.values) = (yyvsp[-4].values);
	}
#line 1557 "parse.c"
    break;

  case 25: /* namevalue_conf: %empty  */
#line 264 "parse.y"
        {
		(yyval.values) = NULL;
	}
#line 1565 "parse.c"
    break;

  case 26: /* task_namevalue_conf: ID '=' ID ';'  */
#line 271 "parse.y"
        {
		(yyval.val) = cgroup_config_group_task_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1578 "parse.c"
    break;

  case 27: /* task_namevalue_conf: task_namevalue_conf ID '=' ID ';'  */
#line 280 "parse.y"
        {
		(yyval.val) = (yyvsp[-4].val) && cgroup_config_group_task_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1591 "parse.c"
    break;

  case 28: /* admin_namevalue_conf: ID '=' ID ';'  */
#line 292 "parse.y"
        {
		(yyval.val) = cgroup_config_group_admin_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1604 "parse.c"
    break;

  case 29: /* admin_namevalue_conf: admin_namevalue_conf ID '=' ID ';'  */
#line 301 "parse.y"
        {
		(yyval.val) = (yyvsp[-4].val) && cgroup_config_group_admin_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1617 "parse.c"
    break;

  case 30: /* template_task_namevalue_conf: ID '=' ID ';'  */
#line 313 "parse.y"
        {
		(yyval.val) = template_config_group_task_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1630 "parse.c"
    break;

  case 31: /* template_task_namevalue_conf: template_task_namevalue_conf ID '=' ID ';'  */
#line 322 "parse.y"
        {
		(yyval.val) = (yyvsp[-4].val) && template_config_group_task_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1643 "parse.c"
    break;

  case 32: /* template_admin_namevalue_conf: ID '=' ID ';'  */
#line 334 "parse.y"
        {
		(yyval.val) = template_config_group_admin_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1656 "parse.c"
    break;

  case 33: /* template_admin_namevalue_conf: template_admin_namevalue_conf ID '=' ID ';'  */
#line 343 "parse.y"
        {
		(yyval.val) = (yyvsp[-4].val) && template_config_group_admin_perm((yyvsp[-3].name), (yyvsp[-1].name));
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1669 "parse.c"
    break;

  case 34: /* task_or_admin: TASK '{' task_namevalue_conf '}' admin_conf  */
#line 356 "parse.y"
        {
		(yyval.val) = (yyvsp[-2].val) && (yyvsp[0].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1682 "parse.c"
    break;

  case 35: /* task_or_admin: ADMIN '{' admin_namevalue_conf '}' task_conf  */
#line 365 "parse.y"
        {
		(yyval.val) = (yyvsp[-2].val) && (yyvsp[0].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1695 "parse.c"
    break;

  case 36: /* admin_conf: ADMIN '{' admin_namevalue_conf '}'  */
#line 376 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1708 "parse.c"
    break;

  case 37: /* task_conf: TASK '{' task_namevalue_conf '}'  */
#line 387 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1721 "parse.c"
    break;

  case 38: /* template_admin_conf: ADMIN '{' template_admin_namevalue_conf '}'  */
#line 398 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1734 "parse.c"
    break;

  case 39: /* template_task_conf: TASK '{' template_task_namevalue_conf '}'  */
#line 409 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1747 "parse.c"
    break;

  case 40: /* mountvalue_conf: ID '=' ID ';'  */
#line 421 "parse.y"
        {
		if (!cgroup_config_insert_into_mount_table((yyvsp[-3].name), (yyvsp[-1].name))) {
			cgroup_config_cleanup_mount_table();
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
		(yyval.val) = 1;
	}
#line 1760 "parse.c"
    break;

  case 41: /* mountvalue_conf: mountvalue_conf ID '=' ID ';'  */
#line 430 "parse.y"
        {
		if (!cgroup_config_insert_into_mount_table((yyvsp[-3].name), (yyvsp[-1].name))) {
			cgroup_config_cleanup_mount_table();
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
		(yyval.val) = 1;
	}
#line 1773 "parse.c"
    break;

  case 42: /* mount: MOUNT '{' mountvalue_conf '}'  */
#line 441 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1786 "parse.c"
    break;

  case 43: /* namespace_conf: ID '=' ID ';'  */
#line 453 "parse.y"
        {
		if (!cgroup_config_insert_into_namespace_table((yyvsp[-3].name), (yyvsp[-1].name))) {
			cgroup_config_cleanup_namespace_table();
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
		(yyval.val) = 1;
	}
#line 1799 "parse.c"
    break;

  case 44: /* namespace_conf: namespace_conf ID '=' ID ';'  */
#line 462 "parse.y"
        {
		if (!cgroup_config_insert_into_namespace_table((yyvsp[-3].name), (yyvsp[-1].name))) {
			cgroup_config_cleanup_namespace_table();
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
		(yyval.val) = 1;
	}
#line 1812 "parse.c"
    break;

  case 45: /* namespace: NAMESPACE '{' namespace_conf '}'  */
#line 473 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1825 "parse.c"
    break;

  case 46: /* systemdvalue_conf: ID '=' ID ';'  */
#line 485 "parse.y"
        {
		if (!cgroup_alloc_systemd_opts((yyvsp[-3].name), (yyvsp[-1].name))) {
			cgroup_cleanup_systemd_opts();
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
		(yyval.val) = 1;
	}
#line 1838 "parse.c"
    break;

  case 47: /* systemdvalue_conf: systemdvalue_conf ID '=' ID ';'  */
#line 494 "parse.y"
        {
		if (!cgroup_add_systemd_opts((yyvsp[-3].name), (yyvsp[-1].name))) {
			cgroup_cleanup_systemd_opts();
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
		(yyval.val) = 1;
	}
#line 1851 "parse.c"
    break;

  case 48: /* systemd: SYSTEMD '{' systemdvalue_conf '}'  */
#line 505 "parse.y"
        {
		(yyval.val) = (yyvsp[-1].val);
		if (!(yyval.val)) {
			fprintf(stderr, "parsing failed at line number %d\n", line_no);
			(yyval.val) = ECGCONFIGPARSEFAIL;
			return (yyval.val);
		}
	}
#line 1864 "parse.c"
    break;


#line 1868 "parse.c"

      default: break;
    }
  /* User semantic actions sometimes alter yychar, and that requires
     that yytoken be updated with the new translation.  We take the
     approach of translating immediately before every use of yytoken.
     One alternative is translating here after every semantic action,
     but that translation would be missed if the semantic action invokes
     YYABORT, YYACCEPT, or YYERROR immediately after altering yychar or
     if it invokes YYBACKUP.  In the case of YYABORT or YYACCEPT, an
     incorrect destructor might then be invoked immediately.  In the
     case of YYERROR or YYBACKUP, subsequent parser actions might lead
     to an incorrect destructor call or verbose syntax error message
     before the lookahead is translated.  */
  YY_SYMBOL_PRINT ("-> $$ =", YY_CAST (yysymbol_kind_t, yyr1[yyn]), &yyval, &yyloc);

  YYPOPSTACK (yylen);
  yylen = 0;

  *++yyvsp = yyval;

  /* Now 'shift' the result of the reduction.  Determine what state
     that goes to, based on the state we popped back to and the rule
     number reduced by.  */
  {
    const int yylhs = yyr1[yyn] - YYNTOKENS;
    const int yyi = yypgoto[yylhs] + *yyssp;
    yystate = (0 <= yyi && yyi <= YYLAST && yycheck[yyi] == *yyssp
               ? yytable[yyi]
               : yydefgoto[yylhs]);
  }

  goto yynewstate;


/*--------------------------------------.
| yyerrlab -- here on detecting error.  |
`--------------------------------------*/
yyerrlab:
  /* Make sure we have latest lookahead translation.  See comments at
     user semantic actions for why this is necessary.  */
  yytoken = yychar == YYEMPTY ? YYSYMBOL_YYEMPTY : YYTRANSLATE (yychar);
  /* If not already recovering from an error, report this error.  */
  if (!yyerrstatus)
    {
      ++yynerrs;
      yyerror (YY_("syntax error"));
    }

  if (yyerrstatus == 3)
    {
      /* If just tried and failed to reuse lookahead token after an
         error, discard it.  */

      if (yychar <= YYEOF)
        {
          /* Return failure if at end of input.  */
          if (yychar == YYEOF)
            YYABORT;
        }
      else
        {
          yydestruct ("Error: discarding",
                      yytoken, &yylval);
          yychar = YYEMPTY;
        }
    }

  /* Else will try to reuse lookahead token after shifting the error
     token.  */
  goto yyerrlab1;


/*---------------------------------------------------.
| yyerrorlab -- error raised explicitly by YYERROR.  |
`---------------------------------------------------*/
yyerrorlab:
  /* Pacify compilers when the user code never invokes YYERROR and the
     label yyerrorlab therefore never appears in user code.  */
  if (0)
    YYERROR;
  ++yynerrs;

  /* Do not reclaim the symbols of the rule whose action triggered
     this YYERROR.  */
  YYPOPSTACK (yylen);
  yylen = 0;
  YY_STACK_PRINT (yyss, yyssp);
  yystate = *yyssp;
  goto yyerrlab1;


/*-------------------------------------------------------------.
| yyerrlab1 -- common code for both syntax error and YYERROR.  |
`-------------------------------------------------------------*/
yyerrlab1:
  yyerrstatus = 3;      /* Each real token shifted decrements this.  */

  /* Pop stack until we find a state that shifts the error token.  */
  for (;;)
    {
      yyn = yypact[yystate];
      if (!yypact_value_is_default (yyn))
        {
          yyn += YYSYMBOL_YYerror;
          if (0 <= yyn && yyn <= YYLAST && yycheck[yyn] == YYSYMBOL_YYerror)
            {
              yyn = yytable[yyn];
              if (0 < yyn)
                break;
            }
        }

      /* Pop the current state because it cannot handle the error token.  */
      if (yyssp == yyss)
        YYABORT;


      yydestruct ("Error: popping",
                  YY_ACCESSING_SYMBOL (yystate), yyvsp);
      YYPOPSTACK (1);
      yystate = *yyssp;
      YY_STACK_PRINT (yyss, yyssp);
    }

  YY_IGNORE_MAYBE_UNINITIALIZED_BEGIN
  *++yyvsp = yylval;
  YY_IGNORE_MAYBE_UNINITIALIZED_END


  /* Shift the error token.  */
  YY_SYMBOL_PRINT ("Shifting", YY_ACCESSING_SYMBOL (yyn), yyvsp, yylsp);

  yystate = yyn;
  goto yynewstate;


/*-------------------------------------.
| yyacceptlab -- YYACCEPT comes here.  |
`-------------------------------------*/
yyacceptlab:
  yyresult = 0;
  goto yyreturnlab;


/*-----------------------------------.
| yyabortlab -- YYABORT comes here.  |
`-----------------------------------*/
yyabortlab:
  yyresult = 1;
  goto yyreturnlab;


/*-----------------------------------------------------------.
| yyexhaustedlab -- YYNOMEM (memory exhaustion) comes here.  |
`-----------------------------------------------------------*/
yyexhaustedlab:
  yyerror (YY_("memory exhausted"));
  yyresult = 2;
  goto yyreturnlab;


/*----------------------------------------------------------.
| yyreturnlab -- parsing is finished, clean up and return.  |
`----------------------------------------------------------*/
yyreturnlab:
  if (yychar != YYEMPTY)
    {
      /* Make sure we have latest lookahead translation.  See comments at
         user semantic actions for why this is necessary.  */
      yytoken = YYTRANSLATE (yychar);
      yydestruct ("Cleanup: discarding lookahead",
                  yytoken, &yylval);
    }
  /* Do not reclaim the symbols of the rule whose action triggered
     this YYABORT or YYACCEPT.  */
  YYPOPSTACK (yylen);
  YY_STACK_PRINT (yyss, yyssp);
  while (yyssp != yyss)
    {
      yydestruct ("Cleanup: popping",
                  YY_ACCESSING_SYMBOL (+*yyssp), yyvsp);
      YYPOPSTACK (1);
    }
#ifndef yyoverflow
  if (yyss != yyssa)
    YYSTACK_FREE (yyss);
#endif

  return yyresult;
}

#line 514 "parse.y"

