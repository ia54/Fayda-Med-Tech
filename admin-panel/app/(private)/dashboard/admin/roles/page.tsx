"use client"

import { useState } from "react"
import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Shield, Plus, Search, Edit, Trash2, Users, Lock, MoreHorizontal } from "lucide-react"
import { 
  useGetRolesQuery, 
  useGetRoleQuery,
  useCreateRoleMutation, 
  useUpdateRoleMutation,
  useDeleteRoleMutation, 
  useGetPermissionsQuery, 
  useCreatePermissionMutation, 
  useUpdatePermissionMutation, 
  useDeletePermissionMutation 
} from "@/store/api/roleApiSlice"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"

import { useAuth } from "@/hooks/useAuth";

export default function RolesPermissionsPage() {
  const { user } = useAuth();
  const isFirmAdmin = user?.role === ROLES.FIRM_ADMIN;
  const [searchTerm, setSearchTerm] = useState("")
  const [isAddRoleDialogOpen, setIsAddRoleDialogOpen] = useState(false)
  const [isEditRoleDialogOpen, setIsEditRoleDialogOpen] = useState(false)
  const [isAddPermissionDialogOpen, setIsAddPermissionDialogOpen] = useState(false)
  const [isEditPermissionDialogOpen, setIsEditPermissionDialogOpen] = useState(false)
  const [selectedRole, setSelectedRole] = useState<any>(null)
  const [selectedPermission, setSelectedPermission] = useState<any>(null)

  // Forms state
  const [newRole, setNewRole] = useState({ name: "", description: "", permissions: [] as string[], color: "default" })
  const [editRoleData, setEditRoleData] = useState({ name: "", description: "", permissions: [] as string[], color: "default" })
  const [newPermission, setNewPermission] = useState({ name: "", description: "", category: "General" })
  const [editPermissionData, setEditPermissionData] = useState({ name: "", description: "", category: "" })

  // API Hooks
  const { data: rolesData, isLoading: rolesLoading } = useGetRolesQuery({})
  const { data: permissionsData, isLoading: permissionsLoading } = useGetPermissionsQuery({})
  const [createRole] = useCreateRoleMutation()
  const [updateRole] = useUpdateRoleMutation()
  const [createPermission] = useCreatePermissionMutation()
  const [updatePermission] = useUpdatePermissionMutation()
  const [deleteRole] = useDeleteRoleMutation()
  const [deletePermission] = useDeletePermissionMutation()

  const roles = rolesData?.roles || []
  const permissions = permissionsData?.permissions || []
  const permissionCategories = permissionsData?.categories || []

  const handleCreateRole = async () => {
    try {
      await createRole(newRole).unwrap()
      setIsAddRoleDialogOpen(false)
      setNewRole({ name: "", description: "", permissions: [], color: "default" })
    } catch (err) {
      console.error("Failed to create role:", err)
    }
  }

  const handleUpdateRole = async () => {
    try {
      await updateRole({ id: selectedRole.id, ...editRoleData }).unwrap()
      setIsEditRoleDialogOpen(false)
    } catch (err) {
      console.error("Failed to update role:", err)
    }
  }

  const handleCreatePermission = async () => {
    try {
      await createPermission(newPermission).unwrap()
      setIsAddPermissionDialogOpen(false)
      setNewPermission({ name: "", description: "", category: "General" })
    } catch (err) {
      console.error("Failed to create permission:", err)
    }
  }

  const handleUpdatePermission = async () => {
    try {
      await updatePermission({ id: selectedPermission.id, ...editPermissionData }).unwrap()
      setIsEditPermissionDialogOpen(false)
    } catch (err) {
      console.error("Failed to update permission:", err)
    }
  }

  const handleDeleteRole = async (id: number) => {
    if (confirm("Are you sure you want to delete this role?")) {
      await deleteRole(id)
    }
  }

  const handleDeletePermission = async (id: number) => {
    if (confirm("Are you sure you want to delete this permission?")) {
      await deletePermission(id)
    }
  }

  const filteredRoles = roles.filter(
    (role: any) =>
      role.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      role.description?.toLowerCase().includes(searchTerm.toLowerCase()),
  )

  const filteredPermissions = permissions.filter(
    (permission: any) =>
      permission.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      permission.description?.toLowerCase().includes(searchTerm.toLowerCase()),
  )

  if (rolesLoading || permissionsLoading) {
    return (
      <div className="flex h-[400px] items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
      </div>
    )
  }

  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <div className="min-h-screen bg-transparent overflow-x-hidden">
        <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-8 max-w-full overflow-x-hidden">
          {/* Header */}
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
                <Shield className="h-8 w-8" />
                Roles & Permissions
              </h1>
              <p className="text-muted-foreground">Manage system roles and permission assignments</p>
            </div>
          <div className="flex gap-2">
            <Dialog open={isAddPermissionDialogOpen} onOpenChange={setIsAddPermissionDialogOpen}>
              <DialogTrigger asChild>
                <Button variant="outline">
                  <Lock className="h-4 w-4 mr-2" />
                  Add Permission
                </Button>
              </DialogTrigger>
              <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                  <DialogTitle>Create New Permission</DialogTitle>
                  <DialogDescription>Add a new permission to the system</DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="permission-name">Permission Name</Label>
                    <Input 
                      id="permission-name" 
                      placeholder="e.g., claim.create" 
                      value={newPermission.name}
                      onChange={(e) => setNewPermission({...newPermission, name: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="permission-description">Description</Label>
                    <Input 
                      id="permission-description" 
                      placeholder="Brief description of permission" 
                      value={newPermission.description}
                      onChange={(e) => setNewPermission({...newPermission, description: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="permission-category">Category</Label>
                    <Input 
                      id="permission-category" 
                      placeholder="e.g., Claims, Users, Reports" 
                      value={newPermission.category}
                      onChange={(e) => setNewPermission({...newPermission, category: e.target.value})}
                    />
                  </div>
                </div>
                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={() => setIsAddPermissionDialogOpen(false)}>
                    Cancel
                  </Button>
                  <Button
                    onClick={handleCreatePermission}
                    className="bg-primary hover:bg-primary/90"
                  >
                    Create Permission
                  </Button>
                </div>
              </DialogContent>
            </Dialog>

            <Dialog open={isEditPermissionDialogOpen} onOpenChange={setIsEditPermissionDialogOpen}>
              <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                  <DialogTitle>Edit Permission</DialogTitle>
                  <DialogDescription>Update the permission details</DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="edit-permission-name">Permission Name</Label>
                    <Input 
                      id="edit-permission-name" 
                      value={editPermissionData.name}
                      onChange={(e) => setEditPermissionData({...editPermissionData, name: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-permission-description">Description</Label>
                    <Input 
                      id="edit-permission-description" 
                      value={editPermissionData.description}
                      onChange={(e) => setEditPermissionData({...editPermissionData, description: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-permission-category">Category</Label>
                    <Input 
                      id="edit-permission-category" 
                      value={editPermissionData.category}
                      onChange={(e) => setEditPermissionData({...editPermissionData, category: e.target.value})}
                    />
                  </div>
                </div>
                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={() => setIsEditPermissionDialogOpen(false)}>
                    Cancel
                  </Button>
                  <Button
                    onClick={handleUpdatePermission}
                    className="bg-primary hover:bg-primary/90"
                  >
                    Save Changes
                  </Button>
                </div>
              </DialogContent>
            </Dialog>

            <Dialog open={isAddRoleDialogOpen} onOpenChange={setIsAddRoleDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-primary hover:bg-primary/90">
                  <Plus className="h-4 w-4 mr-2" />
                  Add Role
                </Button>
              </DialogTrigger>
              <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                  <DialogTitle>Create New Role</DialogTitle>
                  <DialogDescription>Define a new role with specific permissions</DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="role-name">Role Name</Label>
                    <Input 
                      id="role-name" 
                      placeholder="Enter role name" 
                      value={newRole.name}
                      onChange={(e) => setNewRole({...newRole, name: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="role-description">Description</Label>
                    <Textarea
                      id="role-description"
                      placeholder="Describe the role's purpose and responsibilities"
                      rows={3}
                      value={newRole.description}
                      onChange={(e) => setNewRole({...newRole, description: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>Permissions</Label>
                    <div className="border rounded-lg p-4 max-h-60 overflow-y-auto">
                      <div className="space-y-4">
                        {permissionCategories.map((category: any) => (
                          <div key={category.name}>
                            <h4 className="font-medium text-sm mb-2">{category.name}</h4>
                            <div className="space-y-2 ml-4">
                              {permissions
                                .filter((p: any) => p.category === category.name)
                                .map((permission: any) => (
                                  <div key={permission.id} className="flex items-center space-x-2">
                                    <Checkbox 
                                      id={`perm-${permission.id}`} 
                                      checked={newRole.permissions.includes(permission.name)}
                                      onCheckedChange={(checked) => {
                                        if (checked) {
                                          setNewRole({...newRole, permissions: [...newRole.permissions, permission.name]})
                                        } else {
                                          setNewRole({...newRole, permissions: newRole.permissions.filter(p => p !== permission.name)})
                                        }
                                      }}
                                    />
                                    <Label htmlFor={`perm-${permission.id}`} className="text-sm">
                                      <span className="font-mono text-xs">{permission.name}</span>
                                      <span className="text-muted-foreground ml-2">{permission.description}</span>
                                    </Label>
                                  </div>
                                ))}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={() => setIsAddRoleDialogOpen(false)}>
                    Cancel
                  </Button>
                  <Button onClick={handleCreateRole} className="bg-primary hover:bg-primary/90">
                    Create Role
                  </Button>
                </div>
              </DialogContent>
            </Dialog>

            <Dialog open={isEditRoleDialogOpen} onOpenChange={setIsEditRoleDialogOpen}>
              <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                  <DialogTitle>Edit Role</DialogTitle>
                  <DialogDescription>Update the role definition and permissions</DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="edit-role-name">Role Name</Label>
                    <Input 
                      id="edit-role-name" 
                      value={editRoleData.name}
                      onChange={(e) => setEditRoleData({...editRoleData, name: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-role-description">Description</Label>
                    <Textarea
                      id="edit-role-description"
                      rows={3}
                      value={editRoleData.description}
                      onChange={(e) => setEditRoleData({...editRoleData, description: e.target.value})}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>Permissions</Label>
                    <div className="border rounded-lg p-4 max-h-60 overflow-y-auto">
                      <div className="space-y-4">
                        {permissionCategories.map((category: any) => (
                          <div key={category.name}>
                            <h4 className="font-medium text-sm mb-2">{category.name}</h4>
                            <div className="space-y-2 ml-4">
                              {permissions
                                .filter((p: any) => p.category === category.name)
                                .map((permission: any) => (
                                  <div key={permission.id} className="flex items-center space-x-2">
                                    <Checkbox 
                                      id={`edit-perm-${permission.id}`} 
                                      checked={editRoleData.permissions.includes(permission.name)}
                                      onCheckedChange={(checked) => {
                                        if (checked) {
                                          setEditRoleData({...editRoleData, permissions: [...editRoleData.permissions, permission.name]})
                                        } else {
                                          setEditRoleData({...editRoleData, permissions: editRoleData.permissions.filter(p => p !== permission.name)})
                                        }
                                      }}
                                    />
                                    <Label htmlFor={`edit-perm-${permission.id}`} className="text-sm">
                                      <span className="font-mono text-xs">{permission.name}</span>
                                      <span className="text-muted-foreground ml-2">{permission.description}</span>
                                    </Label>
                                  </div>
                                ))}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={() => setIsEditRoleDialogOpen(false)}>
                    Cancel
                  </Button>
                  <Button onClick={handleUpdateRole} className="bg-primary hover:bg-primary/90">
                    Save Changes
                  </Button>
                </div>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Roles</CardTitle>
              <Shield className="h-4 w-4 text-primary" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{roles.length}</div>
              <p className="text-xs text-muted-foreground">Active system roles</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Permissions</CardTitle>
              <Lock className="h-4 w-4 text-accent" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{permissions.length}</div>
              <p className="text-xs text-muted-foreground">Granular permissions</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Users Assigned</CardTitle>
              <Users className="h-4 w-4 text-chart-3" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{roles.reduce((acc: number, r: any) => acc + (r.userCount || 0), 0)}</div>
              <p className="text-xs text-muted-foreground">Total role assignments</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Categories</CardTitle>
              <Shield className="h-4 w-4 text-chart-4" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{permissionCategories.length}</div>
              <p className="text-xs text-muted-foreground">Permission categories</p>
            </CardContent>
          </Card>
        </div>

        {/* Main Content */}
        <Tabs defaultValue="roles" className="space-y-6">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="roles">Roles Management</TabsTrigger>
            <TabsTrigger value="permissions">Permissions</TabsTrigger>
            <TabsTrigger value="assignments">Role Assignments</TabsTrigger>
          </TabsList>

          <TabsContent value="roles" className="space-y-6">
            <Card className="hover:shadow-lg transition-all duration-300 bg-card/80 backdrop-blur-sm">
              <CardHeader>
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                  <div>
                    <CardTitle>System Roles</CardTitle>
                    <CardDescription>Manage roles and their permissions</CardDescription>
                  </div>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                      placeholder="Search roles..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-10 w-full md:w-80"
                    />
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Role Name</TableHead>
                      <TableHead>Description</TableHead>
                      <TableHead>Users</TableHead>
                      <TableHead>Permissions</TableHead>
                      <TableHead>Last Updated</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredRoles.map((role: any) => (
                      <TableRow key={role.id} className="hover:bg-muted/50">
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-2">
                            <Badge variant={role.color as any}>{role.name}</Badge>
                          </div>
                        </TableCell>
                        <TableCell className="max-w-xs">
                          <p className="text-sm truncate">{role.description}</p>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Users className="h-4 w-4 text-muted-foreground" />
                            <span>{role.userCount}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {(role.permissions || []).slice(0, 2).map((perm: string, i: number) => (
                              <Badge key={i} variant="outline" className="text-xs">
                                {perm}
                              </Badge>
                            ))}
                            {(role.permissions || []).length > 2 && (
                              <Badge variant="outline" className="text-xs">
                                +{role.permissions.length - 2}
                              </Badge>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {new Date(role.updated_at).toLocaleDateString()}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2">
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              className="h-8 w-8 p-0"
                              onClick={() => {
                                setSelectedRole(role)
                                setEditRoleData({
                                  name: role.name,
                                  description: role.description || "",
                                  permissions: role.permissions || [],
                                  color: role.color || "default"
                                })
                                setIsEditRoleDialogOpen(true)
                              }}
                            >
                              <Edit className="h-4 w-4" />
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              className="h-8 w-8 p-0"
                              onClick={() => handleDeleteRole(role.id)}
                            >
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="permissions" className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {permissionCategories.map((category: any) => (
                <Card
                  key={category.name}
                  className="hover:shadow-lg transition-all duration-300 bg-card/80 backdrop-blur-sm"
                >
                  <CardHeader className="pb-3">
                    <CardTitle className="text-lg flex items-center justify-between">
                      {category.name}
                      <Badge variant="outline">{category.count}</Badge>
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-2">
                      {permissions
                        .filter((p: any) => p.category === category.name)
                        .map((permission: any) => (
                          <div
                            key={permission.id}
                            className="p-2 rounded-lg bg-muted/50 hover:bg-muted/70 transition-colors"
                          >
                            <div className="flex items-center justify-between">
                              <div>
                                <p className="font-mono text-xs font-medium">{permission.name}</p>
                                <p className="text-xs text-muted-foreground">{permission.description}</p>
                              </div>
                              <div className="flex gap-1">
                                <Button variant="ghost" size="sm" onClick={() => {
                                  setSelectedPermission(permission)
                                  setEditPermissionData({
                                    name: permission.name,
                                    description: permission.description || "",
                                    category: permission.category
                                  })
                                  setIsEditPermissionDialogOpen(true)
                                }}>
                                  <Edit className="h-3 w-3" />
                                </Button>
                                <Button variant="ghost" size="sm" onClick={() => handleDeletePermission(permission.id)}>
                                  <Trash2 className="h-3 w-3 text-destructive" />
                                </Button>
                              </div>
                            </div>
                          </div>
                        ))}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </TabsContent>

          <TabsContent value="assignments" className="space-y-6">
            <Card className="bg-card/50 backdrop-blur-sm border-border/50">
              <CardHeader>
                <CardTitle>Role Assignments</CardTitle>
                <CardDescription>View and manage user role assignments</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Permission</TableHead>
                      <TableHead>Description</TableHead>
                      <TableHead>Category</TableHead>
                      <TableHead>Assigned Roles</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredPermissions.map((permission: any) => (
                      <TableRow key={permission.id}>
                        <TableCell className="font-mono text-sm">{permission.name}</TableCell>
                        <TableCell>{permission.description}</TableCell>
                        <TableCell>
                          <Badge variant="outline">{permission.category}</Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {roles
                              .filter((r: any) => (r.permissions || []).includes(permission.name))
                              .map((role: any, i: number) => (
                                <Badge key={i} variant="secondary" className="text-xs">
                                  {role.name}
                                </Badge>
                              ))}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-2">
                            <Button variant="ghost" size="sm" onClick={() => {
                                  setSelectedPermission(permission)
                                  setEditPermissionData({
                                    name: permission.name,
                                    description: permission.description || "",
                                    category: permission.category
                                  })
                                  setIsEditPermissionDialogOpen(true)
                                }}>
                              <Edit className="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" onClick={() => handleDeletePermission(permission.id)}>
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
    </ProtectedRoute>
  )
}