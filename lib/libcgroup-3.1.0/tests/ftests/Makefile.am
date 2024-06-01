# SPDX-License-Identifier: LGPL-2.1-only
#
# libcgroup functional tests Makefile.am
#
# Copyright (c) 2019-2021 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

TESTS = ftests-wrapper.sh

EXTRA_DIST_PYTHON_UTILS = \
			  cgroup.py \
			  config.py \
			  consts.py \
			  container.py \
			  controller.py \
			  ftests.py \
			  __init__.py \
			  log.py \
			  process.py \
			  run.py \
			  systemd.py \
			  utils.py

EXTRA_DIST_PYTHON_TESTS = \
			  001-cgget-basic_cgget_v1.py \
			  002-cgdelete-recursive_delete.py \
			  003-cgget-basic_cgget_v2.py \
			  004-cgsnapshot-basic_snapshot_v1.py \
			  005-cgsnapshot-basic_snapshot_v2.py \
			  006-cgrules-basic_cgrules_v1.py \
			  007-cgrules-basic_cgrules_v2.py \
			  008-cgget-multiple_r_flags.py \
			  009-cgget-g_flag_controller_only.py \
			  010-cgget-g_flag_controller_and_cgroup.py \
			  011-cgget-r_flag_two_cgroups.py \
			  012-cgget-multiple_r_flags2.py \
			  013-cgget-multiple_g_flags.py \
			  014-cgget-a_flag.py \
			  015-cgget-multiline_r_flag.py \
			  016-cgget-invalid_options.py \
			  017-cgconfig-load_file.py \
			  018-cgconfig-load_dir.py \
			  019-cgconfig-uidgid_dperm_fperm.py \
			  020-cgconfig-tasks_perms_owner.py \
			  021-cgconfig-invalid_options.py \
			  022-cgset-multiple_r_flag.py \
			  023-cgset-copy_from.py \
			  025-cgset-multiple_cgroups.py \
			  026-cgset-multiple_r_multiple_cgroup.py \
			  027-cgset-invalid_options.py \
			  029-lssubsys-basic_lssubsys.py \
			  030-lssubsys-lssubsys_all.py \
			  031-lscgroup-g_flag.py \
			  032-lscgroup-multiple_g_flags.py \
			  033-cgget-no_flags.py \
			  034-cgexec-basic_cgexec.py \
			  035-cgset-set_cgroup_type.py \
			  036-cgset-multi_thread.py \
			  037-cgxget-cpu_settings.py \
			  038-cgxget-cpuset_settings.py \
			  039-pybindings-cgxget.py \
			  040-pybindings-cgxset.py \
			  041-pybindings-library_version.py \
			  042-cgxget-unmappable.py \
			  043-cgcreate-empty_controller.py \
			  044-pybindings-cgcreate_empty_controller.py \
			  045-pybindings-list_mount_points.py \
			  046-cgexec-empty_controller.py \
			  047-cgcreate-delete_cgrp_shared_mnt.py \
			  048-pybindings-get_cgroup_mode.py \
			  049-sudo-systemd_create_scope.py \
			  050-sudo-systemd_create_scope_w_pid.py \
			  051-sudo-cgroup_get_cgroup.py \
			  052-sudo-cgroup_attach_task.py \
			  053-sudo-cgroup_attach_task_pid.py \
			  054-sudo-set_uid_gid_v2.py \
			  055-sudo-set_uid_gid_v1.py \
			  056-sudo-set_permissions_v2.py \
			  057-sudo-set_permissions_v1.py \
			  058-sudo-systemd_create_scope2.py \
			  059-sudo-invalid_systemd_create_scope2.py \
			  060-sudo-cgconfigparser-systemd.py \
			  061-sudo-g_flag_controller_only_systemd-v1.py \
			  062-sudo-g_flag_controller_only_systemd-v2.py \
			  063-sudo-systemd_cgset-v1.py \
			  064-sudo-systemd_cgset-v2.py \
			  065-sudo-systemd_cgclassify-v1.py \
			  066-sudo-systemd_cgclassify-v2.py \
			  067-sudo-systemd_cgexec-v1.py \
			  068-sudo-systemd_cgexec-v2.py \
			  069-sudo-systemd_cgxget-cpu-settings-v1.py \
			  070-sudo-systemd_cgxget-cpu-settings-v2.py \
			  071-sudo-set_default_systemd_cgroup.py \
			  072-pybindings-cgroup_get_cgroup.py \
			  073-pybindings-cgroup_add_all_controllers-v2.py \
			  074-pybindings-cgroup_add_all_controllers-v1.py \
			  075-pybindings-cgroup_compare_cgroup.py \
			  076-cgconfig-auto_convert.py \
			  077-pybindings-cgroup_get_procs.py \
			  078-sudo-cgcreate_systemd_scope.py \
			  079-sudo-cgcreate_default_systemd_scope.py \
			  080-kernel-domain_invalid.py \
			  081-pybindings-cgrp_get_curr_ctrl_path-v1.py \
			  082-pybindings-cgrp_get_curr_ctrl_path-v2.py \
			  083-pybindings-helpers_cgroup_mode.py \
			  084-sudo-cgcreate_systemd_scope_pid.py \
			  085-sudo-cgexec_systemd_scope.py \
			  086-sudo-systemd_cmdline_example.py \
			  087-sudo-move_pid.py \
			  088-sudo-cgclassify_systemd_scope.py \
			  998-cgdelete-non-existing-shared-mnt-cgroup-v1.py
# Intentionally omit the stress test from the extra dist
# 999-stress-cgroup_init.py

EXTRA_DIST = README.md ftests-wrapper.sh \
	     ${EXTRA_DIST_PYTHON_UTILS} ${EXTRA_DIST_PYTHON_TESTS}

clean-local: clean-local-check
.PHONY: clean-local-check
clean-local-check:
	-rm -f *.pyc
