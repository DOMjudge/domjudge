/* SPDX-License-Identifier: LGPL-2.1-only */
/**
 * libcgroup googletest for parse_r_flag() in cgset
 *
 * Copyright (c) 2021 Oracle and/or its affiliates.
 * Author: Tom Hromatka <tom.hromatka@oracle.com>
 */

#include <ftw.h>

#include "gtest/gtest.h"

#include "libcgroup-internal.h"
#include "tools-common.h"

static const char * const PARENT_DIR = "test016cgset/";

static const char * const NAME = "io.max";
static const char * const VALUE = "\"8:16 wbps=1024\"";

class CgsetParseRFlagTest : public ::testing::Test {
};

TEST_F(CgsetParseRFlagTest, EqualCharInValue)
{
	struct control_value name_value;
	char name_value_str[4092];
	int ret;

	ret = snprintf(name_value_str, sizeof(name_value_str) -1,
		       "%s=%s", NAME, VALUE);
	ASSERT_GT(ret, 0);

	ret = parse_r_flag("cgset", name_value_str, &name_value);
	ASSERT_EQ(ret, 0);

	ASSERT_STREQ(name_value.name, NAME);
	ASSERT_STREQ(name_value.value, VALUE);
}
