import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Bell, Mail, MessageSquare, Search, Filter, Eye, Award as MarkAsRead } from "lucide-react"

export default function NotificationsPage() {
  const notifications = [
    {
      id: 1,
      type: "email",
      subject: "Claim Submitted - CASE-2024-001",
      sender: "FAYDA Wellness Pharmacy",
      message: "New claim has been submitted for John Smith case",
      timestamp: "2024-01-15 14:30",
      status: "unread",
      priority: "normal",
    },
    {
      id: 2,
      type: "sms",
      subject: "Payment Received",
      sender: "FAYDA Wellness Pharmacy",
      message: "Payment of $2,450 received for claim CL-2024-001",
      timestamp: "2024-01-15 13:45",
      status: "read",
      priority: "high",
    },
    {
      id: 3,
      type: "email",
      subject: "Lien Agreement Signed",
      sender: "FAYDA Wellness Pharmacy",
      message: "Lien agreement LN-2024-003 has been signed by all parties",
      timestamp: "2024-01-15 12:20",
      status: "unread",
      priority: "normal",
    },
    {
      id: 4,
      type: "notification",
      subject: "Appeal Submitted",
      sender: "FAYDA Wellness Pharmacy",
      message: "Appeal letter has been submitted for denied claim CL-2024-002",
      timestamp: "2024-01-15 11:15",
      status: "read",
      priority: "high",
    },
    {
      id: 5,
      type: "email",
      subject: "Document Upload Required",
      sender: "FAYDA Wellness Pharmacy",
      message: "Additional documentation needed for claim CL-2024-005",
      timestamp: "2024-01-15 10:30",
      status: "unread",
      priority: "urgent",
    },
  ]

  const templates = [
    { id: 1, name: "Claim Status Update", language: "English", category: "Claims", lastUsed: "2024-01-15" },
    { id: 2, name: "تحديث حالة المطالبة", language: "Arabic", category: "Claims", lastUsed: "2024-01-14" },
    {
      id: 3,
      name: "Actualización de Estado de Reclamo",
      language: "Spanish",
      category: "Claims",
      lastUsed: "2024-01-14",
    },
    { id: 4, name: "Payment Confirmation", language: "English", category: "Payments", lastUsed: "2024-01-13" },
    { id: 5, name: "Settlement Notice", language: "English", category: "Legal", lastUsed: "2024-01-12" },
  ]

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white text-balance">Notifications & Messages</h1>
            <p className="text-gray-600 dark:text-slate-300 mt-2">Template messages and communications from providers</p>
          </div>
          <div className="flex gap-3">
            <Button variant="outline" className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent text-emerald-700 dark:text-white">
              <Filter className="w-4 h-4 mr-2" />
              Filter
            </Button>
            <Button variant="outline" className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent text-emerald-700 dark:text-white">
              <MarkAsRead className="w-4 h-4 mr-2" />
              Mark All Read
            </Button>
          </div>
        </div>

        {/* Overview Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Bell className="w-4 h-4 text-emerald-600 dark:text-slate-300" />
                Total Notifications
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">247</div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">This month</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Mail className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                Unread Messages
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">23</div>
              <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">Requires attention</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <MessageSquare className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                Urgent Notifications
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">5</div>
              <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">High priority</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Bell className="w-4 h-4 text-green-600 dark:text-green-400" />
                Response Rate
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">94%</div>
              <p className="text-xs text-green-600 dark:text-green-400 mt-1">Average response</p>
            </CardContent>
          </Card>
        </div>

        {/* Notifications Inbox */}
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-gray-900 dark:text-white">Notification Inbox</CardTitle>
            <CardDescription className="dark:text-slate-300">Messages and updates from healthcare providers</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col md:flex-row gap-4 mb-6">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input placeholder="Search notifications..." className="pl-10" />
              </div>
              <Select>
                <SelectTrigger className="w-full md:w-48">
                  <SelectValue placeholder="Filter by type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Types</SelectItem>
                  <SelectItem value="email">Email</SelectItem>
                  <SelectItem value="sms">SMS</SelectItem>
                  <SelectItem value="notification">System</SelectItem>
                </SelectContent>
              </Select>
              <Select>
                <SelectTrigger className="w-full md:w-48">
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="unread">Unread</SelectItem>
                  <SelectItem value="read">Read</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Sender</TableHead>
                    <TableHead>Message</TableHead>
                    <TableHead>Timestamp</TableHead>
                    <TableHead>Priority</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {notifications.map((notification) => (
                    <TableRow
                      key={notification.id}
                      className={`hover:bg-emerald-50/50 dark:hover:bg-emerald-900/20 border-emerald-100 dark:border-emerald-900/30 ${notification.status === "unread" ? "bg-blue-50/30 dark:bg-blue-900/20" : ""}`}
                    >
                      <TableCell>
                        <Badge variant="outline">
                          {notification.type === "email" && <Mail className="w-3 h-3 mr-1" />}
                          {notification.type === "sms" && <MessageSquare className="w-3 h-3 mr-1" />}
                          {notification.type === "notification" && <Bell className="w-3 h-3 mr-1" />}
                          {notification.type}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-medium">{notification.subject}</TableCell>
                      <TableCell>{notification.sender}</TableCell>
                      <TableCell className="max-w-xs truncate">{notification.message}</TableCell>
                      <TableCell className="font-mono text-sm">{notification.timestamp}</TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            notification.priority === "urgent"
                              ? "destructive"
                              : notification.priority === "high"
                                ? "secondary"
                                : "outline"
                          }
                        >
                          {notification.priority}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant={notification.status === "unread" ? "default" : "outline"}>
                          {notification.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm">
                          <Eye className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        {/* Message Templates */}
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-gray-900 dark:text-white">Message Templates</CardTitle>
            <CardDescription className="dark:text-slate-300">Multilingual templates used by providers</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Template Name</TableHead>
                    <TableHead>Language</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Last Used</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {templates.map((template) => (
                    <TableRow key={template.id} className="hover:bg-emerald-50/50">
                      <TableCell className="font-medium">{template.name}</TableCell>
                      <TableCell>{template.language}</TableCell>
                      <TableCell>
                        <Badge variant="outline">{template.category}</Badge>
                      </TableCell>
                      <TableCell>{template.lastUsed}</TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm">
                          <Eye className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
