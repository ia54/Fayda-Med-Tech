"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { TrendingDown, TrendingUp } from "lucide-react";
import { CardStatsConfig, StatCard } from "./types";

interface StatsCardsProps {
  config: CardStatsConfig;
}

export function StatsCards({ config }: StatsCardsProps) {
  const { stats, className } = config;

  if (!stats || stats.length === 0) {
    return null;
  }

  return (
    <div
      className={cn("grid gap-4 md:grid-cols-2 lg:grid-cols-4", className)}
    >
      {stats.map((stat) => (
        <StatCardComponent key={stat.id} stat={stat} />
      ))}
    </div>
  );
}

interface StatCardComponentProps {
  stat: StatCard;
}

function StatCardComponent({ stat }: StatCardComponentProps) {
  const { title, value, icon, description, trend, onClick } = stat;

  return (
    <Card
      className={cn(
        "bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1",
        onClick && "cursor-pointer"
      )}
      onClick={onClick}
    >
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">{title}</CardTitle>
        {icon && <div className="text-emerald-600 dark:text-slate-300">{icon}</div>}
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold text-emerald-900 dark:text-white">{value}</div>
        {(description || trend) && (
          <div className="flex items-center gap-2 mt-1">
            {trend && (
              <div
                className={cn(
                  "flex items-center gap-1 text-xs font-medium",
                  trend.isPositive ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400"
                )}
              >
                {trend.isPositive ? (
                  <TrendingUp className="h-3 w-3" />
                ) : (
                  <TrendingDown className="h-3 w-3" />
                )}
                {Math.abs(trend.value)}%
              </div>
            )}
            {description && (
              <p className="text-xs text-emerald-600 dark:text-slate-300">{description}</p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
