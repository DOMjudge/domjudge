# SPDX-License-Identifier: LGPL-2.1-only
#
# Libcgroup Python Bindings
#
# Copyright (c) 2021-2022 Oracle and/or its affiliates.
# Author: Tom Hromatka <tom.hromatka@oracle.com>
#

# cython: language_level = 3str

""" Python bindings for the libcgroup library
"""

__author__ = 'Tom Hromatka <tom.hromatka@oracle.com>'
__date__ = "25 October 2021"

from posix.types cimport pid_t, mode_t
from libc.stdlib cimport malloc, free
from libc.string cimport strcpy
cimport cgroup

CONTROL_NAMELEN_MAX = 32

cdef class Version:
    CGROUP_UNK = cgroup.CGROUP_UNK
    CGROUP_V1 = cgroup.CGROUP_V1
    CGROUP_V2 = cgroup.CGROUP_V2
    CGROUP_DISK = cgroup.CGROUP_DISK

cdef class Mode:
    CGROUP_MODE_UNK = cgroup.CGROUP_MODE_UNK
    CGROUP_MODE_LEGACY = cgroup.CGROUP_MODE_LEGACY
    CGROUP_MODE_HYBRID = cgroup.CGROUP_MODE_HYBRID
    CGROUP_MODE_UNIFIED = cgroup.CGROUP_MODE_UNIFIED

cdef class SystemdMode:
    CGROUP_SYSTEMD_MODE_FAIL = cgroup.CGROUP_SYSTEMD_MODE_FAIL
    CGROUP_SYSTEMD_MODE_REPLACE = cgroup.CGROUP_SYSTEMD_MODE_REPLACE
    CGROUP_SYSTEMD_MODE_ISOLATE = cgroup.CGROUP_SYSTEMD_MODE_ISOLATE
    CGROUP_SYSTEMD_MODE_IGNORE_DEPS = cgroup.CGROUP_SYSTEMD_MODE_IGNORE_DEPS
    CGROUP_SYSTEMD_MODE_IGNORE_REQS = cgroup.CGROUP_SYSTEMD_MODE_IGNORE_REQS

cdef class LogLevel:
    CGROUP_LOG_CONT = cgroup.CGROUP_LOG_CONT
    CGROUP_LOG_ERROR = cgroup.CGROUP_LOG_ERROR
    CGROUP_LOG_WARNING = cgroup.CGROUP_LOG_WARNING
    CGROUP_LOG_INFO = cgroup.CGROUP_LOG_INFO
    CGROUP_LOG_DEBUG = cgroup.CGROUP_LOG_DEBUG


def c_str(string):
    return bytes(string, "ascii")


def indent(in_str, cnt):
    leading_indent = cnt * ' '
    return ''.join(leading_indent + line for line in in_str.splitlines(True))


class Controller:
    def __init__(self, name):
        self.name = name
        # self.settings maps to
        # struct control_value *values[CG_NV_MAX];
        self.settings = dict()

    def __str__(self):
        out_str = "Controller {}\n".format(self.name)

        for setting_key in self.settings:
            out_str += indent("{} = {}\n".format(setting_key,
                              self.settings[setting_key]), 4)

        return out_str

    def __eq__(self, other):
        if not isinstance(other, Controller):
            return False

        if self.name != other.name:
            return False

        if self.settings != other.settings:
            return False

        return True


cdef class Cgroup:
    """ Python object representing a libcgroup cgroup """
    cdef cgroup.cgroup * _cgp
    cdef public:
        object name, controllers, version

    @staticmethod
    def cgroup_init():
        ret = cgroup.cgroup_init()
        if ret != 0:
            raise RuntimeError("Failed to initialize libcgroup: {}".format(ret))

    def __cinit__(self, name, version):
        Cgroup.cgroup_init()

        self._cgp = cgroup.cgroup_new_cgroup(c_str(name))
        if self._cgp == NULL:
            raise RuntimeError("Failed to create cgroup {}".format(name))

    def __init__(self, name, version):
        """Initialize this cgroup instance

        Arguments:
        name - Name of this cgroup
        version - Version of this cgroup

        Note:
        Does not modify the cgroup sysfs.  Does not read from the cgroup sysfs
        """
        self.name = name
        self.controllers = dict()
        self.version = version

    def __str__(self):
        out_str = "Cgroup {}\n".format(self.name)
        for ctrl_key in self.controllers:
            out_str += indent(str(self.controllers[ctrl_key]), 4)

        return out_str

    def __eq__(self, other):
        if not isinstance(other, Cgroup):
            return False

        if self.name != other.name:
            return False

        if self.version != other.version:
            return False

        if self.controllers != other.controllers:
            return False

        if not self.compare(other):
            return False

        return True

    @staticmethod
    def library_version():
        cdef const cgroup.cgroup_library_version * version

        version = cgroup.cgroup_version()
        return [version.major, version.minor, version.release]

    def add_controller(self, ctrl_name):
        """Add a controller to the Cgroup instance

        Arguments:
        ctrl_name - name of the controller

        Description:
        Adds a controller to the Cgroup instance

        Note:
        Does not modify the cgroup sysfs
        """
        cdef cgroup.cgroup_controller * cgcp

        cgcp = cgroup.cgroup_add_controller(self._cgp,
                                            c_str(ctrl_name))
        if cgcp == NULL:
            raise RuntimeError("Failed to add controller {} to cgroup".format(
                               ctrl_name))

        self.controllers[ctrl_name] = Controller(ctrl_name)

    def add_all_controllers(self):
        """Add all enabled controllers to the Cgroup instance

        Description:
        Adds all enabled controllers (i.e. all controllers in the cgroup's cgroup.controllers
        file) to the Cgroup instance

        Note:
        Reads from the cgroup sysfs
        """
        ret = cgroup.cgroup_add_all_controllers(self._cgp)
        if ret != 0:
            raise RuntimeError("Failed to add all controllers to cgroup")

        ctrl_cnt = cgroup.cgroup_get_controller_count(self._cgp)
        for i in range(0, ctrl_cnt):
            ctrl_ptr = cgroup.cgroup_get_controller_by_index(self._cgp, i)
            ctrl_name = cgroup.cgroup_get_controller_name(ctrl_ptr).decode('ascii')
            self.controllers[ctrl_name] = Controller(ctrl_name)

        self._pythonize_cgroup()

    def add_setting(self, setting_name, setting_value=None):
        """Add a setting to the Cgroup/Controller instance

        Arguments:
        setting_name - name of the cgroup setting, e.g. 'cpu.shares'
        setting_value (optional) - value

        Description:
        Adds a setting/value pair to the Cgroup/Controller instance

        Note:
        Does not modify the cgroup sysfs
        """
        cdef cgroup.cgroup_controller *cgcp

        ctrl_name = setting_name.split('.')[0]

        cgcp = cgroup.cgroup_get_controller(self._cgp,
                                            c_str(ctrl_name))
        if cgcp == NULL:
            self.add_controller(ctrl_name)

            cgcp = cgroup.cgroup_get_controller(self._cgp,
                                                c_str(ctrl_name))
            if cgcp == NULL:
                raise RuntimeError("Failed to get controller {}".format(
                                   ctrl_name))

        if setting_value is None:
            ret = cgroup.cgroup_add_value_string(cgcp,
                      c_str(setting_name), NULL)
        else:
            ret = cgroup.cgroup_add_value_string(cgcp,
                      c_str(setting_name), c_str(setting_value))
        if ret != 0:
            raise RuntimeError("Failed to add setting {}: {}".format(
                               setting_name, ret))

    def _pythonize_cgroup(self):
        """
        Given a populated self._cgp, populate the equivalent Python fields
        """
        cdef char *setting_name
        cdef char *setting_value

        for ctrlr_key in self.controllers:
            cgcp = cgroup.cgroup_get_controller(self._cgp,
                       c_str(self.controllers[ctrlr_key].name))
            if cgcp == NULL:
                raise RuntimeError("Failed to get controller {}".format(
                                   ctrlr_key))

            self.controllers[ctrlr_key] = Controller(ctrlr_key)
            setting_cnt = cgroup.cgroup_get_value_name_count(cgcp)

            for i in range(0, setting_cnt):
                setting_name = cgroup.cgroup_get_value_name(cgcp, i)

                ret = cgroup.cgroup_get_value_string(cgcp,
                          setting_name, &setting_value)
                if ret != 0:
                    raise RuntimeError("Failed to get value {}: {}".format(
                                       setting_name, ret))

                name = setting_name.decode("ascii")
                value = setting_value.decode("ascii").strip()
                self.controllers[ctrlr_key].settings[name] = value

    def convert(self, out_version):
        """Convert this cgroup to another cgroup version

        Arguments:
        out_version - Version to convert to

        Return:
        Returns the converted cgroup instance

        Description:
        Convert this cgroup instance to a cgroup instance of a different
        cgroup version

        Note:
        Does not modify the cgroup sysfs.  Does not read from the cgroup sysfs
        """
        out_cgp = Cgroup(self.name, out_version)
        ret = cgroup.cgroup_convert_cgroup(out_cgp._cgp,
                  out_version, self._cgp, self.version)
        if ret != 0:
            raise RuntimeError("Failed to convert cgroup: {}".format(ret))

        for ctrlr_key in self.controllers:
            out_cgp.controllers[ctrlr_key] = Controller(ctrlr_key)

        out_cgp._pythonize_cgroup()

        return out_cgp

    def cgxget(self, ignore_unmappable=False):
        """Read the requested settings from the cgroup sysfs

        Arguments:
        ignore_unmappable - Ignore cgroup settings that can't be converted
                            from one version to another

        Return:
        Returns the cgroup instance that represents the settings read from
        sysfs

        Description:
        Given this cgroup instance, read the settings/values from the
        cgroup sysfs.  If the read was successful, the settings are
        returned in the return value

        Note:
        Reads from the cgroup sysfs
        """
        cdef bint ignore

        if ignore_unmappable:
            ignore = 1
        else:
            ignore = 0

        ret = cgroup.cgroup_cgxget(&self._cgp, self.version, ignore)
        if ret != 0:
            raise RuntimeError("cgxget failed: {}".format(ret))

        self._pythonize_cgroup()

    def cgxset(self, ignore_unmappable=False):
        """Write this cgroup to the cgroup sysfs

        Arguments:
        ignore_unmappable - Ignore cgroup settings that can't be converted
                            from one version to another

        Description:
        Write the settings/values in this cgroup instance to the cgroup sysfs

        Note:
        Writes to the cgroup sysfs
        """
        if ignore_unmappable:
            ignore = 1
        else:
            ignore = 0

        ret = cgroup.cgroup_cgxset(self._cgp, self.version, ignore)
        if ret != 0:
            raise RuntimeError("cgxset failed: {}".format(ret))

    def create(self, ignore_ownership=True):
        """Write this cgroup to the cgroup sysfs

        Arguments:
        ignore_ownership - if true, all errors are ignored when setting ownership
                           of the group and its tasks file

        Return:
        None
        """
        ret = cgroup.cgroup_create_cgroup(self._cgp, ignore_ownership)
        if ret != 0:
            raise RuntimeError("Failed to create cgroup: {}".format(ret))

    @staticmethod
    def mount_points(version):
        """List cgroup mount points of the specified version

        Arguments:
        version - It specifies the cgroup version

        Return:
        The cgroup mount points in a list

        Description:
        Parse the /proc/mounts and list the cgroup mount points matching the
        version
        """
        cdef char **a

        Cgroup.cgroup_init()

        mount_points = []
        ret = cgroup.cgroup_list_mount_points(version, &a)
        if ret is not 0:
            raise RuntimeError("cgroup_list_mount_points failed: {}".format(ret))

        i = 0
        while a[i]:
            mount_points.append(<str>(a[i]).decode("utf-8"))
            i = i + 1
        return mount_points

    @staticmethod
    def cgroup_mode():
        """Get the cgroup mode (legacy, hybrid, or unified)

        Return:
        The cgroup mode enumeration
        """
        Cgroup.cgroup_init()
        return cgroup.cgroup_setup_mode()

    @staticmethod
    def create_scope(scope_name='libcgroup.scope', slice_name='libcgroup.slice', delegated=True,
                     systemd_mode=SystemdMode.CGROUP_SYSTEMD_MODE_FAIL, pid=None):
        """Create a systemd scope

        Arguments:
        scope_name - name of the scope to be created
        slice_name - name of the slice where the scope should reside
        delegated - if true, then systemd will not manage the cgroup aspects of the scope.  It
                    is up to the user to manage the cgroup settings
        systemd_mode - setting to tell systemd how to handle creation of this scope and
                       resolve conflicts if the scope and/or slice exist
        pid - pid of the process to place in the scope.  If None is provided, libcgroup will
              place a dummy process in the scope

        Description:
        Create a systemd scope under slice_name.  If delegated is true, then systemd will
        not manage the cgroup aspects of the scope.
        """
        cdef cgroup.cgroup_systemd_scope_opts opts

        Cgroup.cgroup_init()

        if delegated:
            opts.delegated = 1
        else:
            opts.delegated = 0

        opts.mode = systemd_mode
        if pid:
            opts.pid = pid
        else:
            opts.pid = -1

        ret = cgroup.cgroup_create_scope(c_str(scope_name), c_str(slice_name), &opts)
        if ret is not 0:
            raise RuntimeError("cgroup_create_scope failed: {}".format(ret))

    def get(self):
        """Get the cgroup information from the cgroup sysfs

        Description:
        Read the cgroup data from the cgroup sysfs filesystem
        """
        cdef cgroup.cgroup_controller *ctrl_ptr

        ret = cgroup.cgroup_get_cgroup(self._cgp)
        if ret is not 0:
            raise RuntimeError("cgroup_get_cgroup failed: {}".format(ret))

        ctrl_cnt = cgroup.cgroup_get_controller_count(self._cgp)
        for i in range(0, ctrl_cnt):
            ctrl_ptr = cgroup.cgroup_get_controller_by_index(self._cgp, i)
            ctrl_name = cgroup.cgroup_get_controller_name(ctrl_ptr).decode('ascii')
            self.controllers[ctrl_name] = Controller(ctrl_name)

        self._pythonize_cgroup()

    def delete(self, ignore_migration=True):
        """Delete the cgroup from the cgroup sysfs

        Arguments:
        ignore_migration - ignore errors caused by migration of tasks to parent cgroup

        Description:
        Delete the cgroup from the cgroup sysfs filesystem
        """
        ret = cgroup.cgroup_delete_cgroup(self._cgp, ignore_migration)
        if ret is not 0:
            raise RuntimeError("cgroup_delete_cgroup failed: {}".format(ret))

    def attach(self, pid=None, root_cgroup=False):
        """Attach a process to a cgroup

        Arguments:
        pid - pid to be attached.  If none, then the current pid is attached
        root_cgroup - if True, then the pid will be attached to the root cgroup

        Description:
        Attach a process to a cgroup
        """
        if pid is None:
            if root_cgroup:
                ret = cgroup.cgroup_attach_task(NULL)
            else:
                ret = cgroup.cgroup_attach_task(self._cgp)
        else:
            if root_cgroup:
                ret = cgroup.cgroup_attach_task_pid(NULL, pid)
            else:
                ret = cgroup.cgroup_attach_task_pid(self._cgp, pid)

        if ret is not 0:
            raise RuntimeError("cgroup_attach_task failed: {}".format(ret))

    def set_uid_gid(self, tasks_uid, tasks_gid, ctrl_uid, ctrl_gid):
        """Set the desired owning uid/gid for the tasks file and the entire cgroup hierarchy

        Arguments:
        tasks_uid - uid that should own the tasks file
        tasks_gid - gid that should own the tasks file
        ctrl_uid - uid to recursively apply to the entire cgroup hierarchy
        ctrl_gid - gid to recursively apply to the entire cgroup hierarchy

        Note:
        Does not modify the cgroup sysfs.  Does not read from the cgroup sysfs.  Applies the
        provided uids and gids to the appropriate uid/gid fields in the cgroup struct.
        """
        ret = cgroup.cgroup_set_uid_gid(self._cgp, tasks_uid, tasks_gid, ctrl_uid, ctrl_gid)
        if ret is not 0:
            raise RuntimeError("cgroup_set_uid_gid failed: {}".format(ret))

    def set_permissions(self, dir_mode, ctrl_mode, task_mode):
        """Set the permission bits on the cgroup

        Arguments:
        dir_mode - permissions to set on the cgroup directory
        ctrl_mode - permissions to set on the files in the directory, except tasks
        task_mode - permissions to set on the tasks file

        Note:
        Does not modify the cgroup sysfs.  Does not read from the cgroup sysfs.  Only the
        in-memory cgroup structure is updated.

        The mode parameters are expected to be of a form defined in the Python stat module [1],
        e.g. stat.S_IWUSR.

        [1] https://docs.python.org/3/library/stat.html
        """
        cdef mode_t dmode = dir_mode
        cdef mode_t cmode = ctrl_mode
        cdef mode_t tmode = task_mode

        cgroup.cgroup_set_permissions(self._cgp, dmode, cmode, tmode)

    def create_scope2(self, ignore_ownership=True, delegated=True,
                      systemd_mode=SystemdMode.CGROUP_SYSTEMD_MODE_FAIL, pid=None):
        """Create a systemd scope using the cgroup instance

        Arguments:
        ignore_ownership - if true, do not modify the owning user/group for the cgroup directory
                           and control files
        delegated - if true, then systemd will not manage the cgroup aspects of the scope.  It
                    is up to the user to manage the cgroup settings
        systemd_mode - setting to tell systemd how to handle creation of this scope and
                       resolve conflicts if the scope and/or slice exist
        pid - pid of the process to place in the scope.  If None is provided, libcgroup will
              place a dummy process in the scope

        Description:
        Create a systemd scope using the cgroup instance in this class.  If delegated is true,
        then systemd will not manage the cgroup aspects of the scope.
        """
        cdef cgroup.cgroup_systemd_scope_opts opts

        if delegated:
            opts.delegated = 1
        else:
            opts.delegated = 0

        opts.mode = systemd_mode
        if pid:
            opts.pid = pid
        else:
            opts.pid = -1

        ret = cgroup.cgroup_create_scope2(self._cgp, ignore_ownership, &opts)
        if ret is not 0:
            raise RuntimeError("cgroup_create_scope2 failed: {}".format(ret))

    @staticmethod
    def _set_default_systemd_cgroup():
        """Set systemd_default_cgroup

        Arguments:
        None

        Description:
        Reads /run/libcgroup/systemd and if the file exists, sets the
        systemd_default_cgroup. Then on all the paths constructed, has
        the systemd_default_cgroup appended to it.  This is used when
        cgroup sub-tree is constructed for systemd delegation.
        """
        Cgroup.cgroup_init()
        ret = cgroup.cgroup_set_default_systemd_cgroup()

        if ret != 1:
            raise RuntimeError('Failed to set the default systemd cgroup')

    @staticmethod
    def write_default_systemd_scope(slice_name, scope_name, set_default=True):
        """Write the provided slice and scope to the libcgroup /var/run file

        Arguments:
        slice_name - Slice name, e.g. libcgroup.slice
        scope_name - Scope name, e.g. database.scope
        set_default - If true, set this as the default path for libcgroup APIs
                      and tools

        Description:
        Write the provided slice and scope to the libcgroup var/run file.  This
        convenience function provides a mechanism for setting a slice/scope as
        the default path within libcgroup.  Any API or cmdline operation will
        utilize this path as the "root" cgroup, but it can be overridden on a
        case-by-case basis.

        So if the default slice/scope is set to "libcgroup.slice/database.scope",
        and the user wants to access "libcgroup.slice/database.scope/foo", then
        they can use the following:

            # Within libcgroup, this will expand to
            # libcgroup.slice/database.scope/foo
            cg = Cgroup('foo')
        """
        ret = cgroup.cgroup_write_systemd_default_cgroup(c_str(slice_name),
                                                         c_str(scope_name))
        if ret != 1:
            raise RuntimeError("Failed to write the default slice/scope")

        if set_default:
            Cgroup._set_default_systemd_cgroup()

    @staticmethod
    def clear_default_systemd_scope():
        """Clear/Remove the default slice/scope from libcgroup

        Description:
        Delete the default slice/scope from the libcgroup var/run file.
        Also delete the internal global variable within libcgroup so that
        the default path is restored back to the root cgroup
        """
        try:
            Cgroup.write_default_systemd_scope('', '', set_default=False)
        except RuntimeError:
            pass

        try:
            Cgroup._set_default_systemd_cgroup()
        except RuntimeError:
            pass

    cdef compare(self, Cgroup other):
        """Compare this cgroup instance with another cgroup instance

        Arguments:
        other - other cgroup instance to be compared

        Return:
        Returns true if the cgroups are equal.  False otherwise

        Description:
        Invokes the libcgroup C function, cgroup_compare_cgroup().
        cgroup_compare_cgroup() walks through the cgroup and compares the
        cgroup, its controllers, and the values/settings within the
        controller.
        """
        ret = cgroup.cgroup_compare_cgroup(self._cgp, other._cgp)
        if ret == 0:
            return True
        else:
            return False

    def get_processes(self):
        """Get the processes in this cgroup

        Return:
        Returns an integer list of PIDs

        Description:
        Invokes the libcgroup C function, cgroup_get_procs().
        cgroup_get_procs() reads each controller's cgroup.procs file
        in the cgroup sysfs.  The results are then combined together
        into a standard Python list of integers.

        Note:
        Reads from the cgroup sysfs
        """
        pid_list = list()
        cdef pid_t *pids
        cdef int size

        if len(self.controllers) == 0:
            ret = cgroup.cgroup_get_procs(c_str(self.name), NULL, &pids, &size)
            if ret is not 0:
                raise RuntimeError("cgroup_get_procs failed: {}".format(ret))

            for i in range(0, size):
                pid_list.append(int(pids[i]))

        for ctrl_key in self.controllers:
            ret = cgroup.cgroup_get_procs(c_str(self.name),
                        c_str(self.controllers[ctrl_key].name), &pids, &size)
            if ret is not 0:
                raise RuntimeError("cgroup_get_procs failed: {}".format(ret))

            for i in range(0, size):
                pid_list.append(int(pids[i]))

        # Remove duplicates
        pid_list = [*set(pid_list)]

        return pid_list

    @staticmethod
    def is_cgroup_mode_legacy():
        """Check if the current setup mode is legacy (v1)

        Return:
        True if the mode is legacy, else false
        """
        return cgroup.is_cgroup_mode_legacy()

    @staticmethod
    def is_cgroup_mode_hybrid():
        """Check if the current setup mode is hybrid (v1/v2)

        Return:
        True if the mode is hybrid, else false
        """
        return cgroup.is_cgroup_mode_hybrid()

    @staticmethod
    def is_cgroup_mode_unified():
        """Check if the current setup mode is unified (v2)

        Return:
        True if the mode is unified, else false
        """
        return cgroup.is_cgroup_mode_unified()

    @staticmethod
    def get_current_controller_path(pid, controller=None):
        """Get the cgroup path of pid, under controller hierarchy

        Return:
        Return cgroup path relative to mount point

        Description:
        Invokes the libcgroup C function, cgroup_get_current_controller_path().
        It parses the /proc/<pid>/cgroup file and returns the cgroup path, if
        the controller matches in the output for cgroup v1 controllers and for
        the cgroup v2 controllers, checks if the cgroup.controllers file has
        the controller enabled.
        """
        cdef char *current_path

        Cgroup.cgroup_init()

        if controller is None:
            ret = cgroup.cgroup_get_current_controller_path(pid, NULL,
                        &current_path)
        elif isinstance(controller, str):
            ret = cgroup.cgroup_get_current_controller_path(pid,
                        c_str(controller), &current_path)
        else:
            raise TypeError("cgroup_get_current_controller_path failed: "
                            "expected controller type string, but passed "
                            "{}".format(type(controller)))

        if ret is not 0:
            raise RuntimeError("cgroup_get_current_controller_path failed :"
                               "{}".format(ret))

        return current_path.decode('ascii')

    @staticmethod
    def move_process(pid, dest_cgname, controller=None):
        """Move a process to the specified cgroup

        Return:
        None

        Description:
        Invokes the libcgroup C function, cgroup_change_cgroup_path().
        It moves a process to the specified cgroup dest_cgname.

        To move the process to a cgroup v1 cgroup, the controller must be
        provided.  For cgroup v2, the controller is optional

        Note:
        * Writes to the cgroup sysfs
        """
        cdef char *controllers[2]

        if not controller:
            ret = cgroup.cgroup_change_cgroup_path(c_str(dest_cgname), pid, NULL)
        elif isinstance(controller, str):
            controllers[0] = <char *>malloc(CONTROL_NAMELEN_MAX)
            strcpy(controllers[0], c_str(controller))
            controllers[1] = NULL

            ret = cgroup.cgroup_change_cgroup_path(c_str(dest_cgname), pid,
                                                   <const char * const *>controllers)
            free(controllers[0])
        else:
            #
            # In the future we could add support for a list of controllers
            #
            raise TypeError("Unsupported controller type: {}".format(type(controller)))

        if ret is not 0:
            raise RuntimeError("cgroup_change_cgroup_path failed :"
                               "{}".format(ret))

    @staticmethod
    def log_level(log_level):
        """Set the libcgroup log level

        Arguments:
        log_level - libcgroup.LogLevel

        Description:
        Set the libcgroup logger to stdout at the specified log_level
        """
        cgroup.cgroup_set_default_logger(log_level)

    def __dealloc__(self):
        cgroup.cgroup_free(&self._cgp)

# vim: set et ts=4 sw=4:
