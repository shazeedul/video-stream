import { Card } from "@/components/ui/card"
import { cn } from "@/lib/utils"
import { TrendingDown, TrendingUp } from "lucide-react"
import * as React from "react"

interface StatProps extends React.HTMLAttributes<HTMLDivElement> {
  title: string
  value: string
  icon?: React.ReactNode
  trend?: "up" | "down"
  trendValue?: string
  description?: string
}

export function Stat({
  title,
  value,
  icon,
  trend,
  trendValue,
  description,
  className,
  ...props
}: StatProps) {
  return (
    <Card className={cn("p-6", className)} {...props}>
      <div className="flex items-center justify-between">
        <div className="space-y-1">
          <p className="text-sm font-medium text-muted-foreground">{title}</p>
          <p className="text-2xl font-bold">{value}</p>
          {description && (
            <p className="text-xs text-muted-foreground">{description}</p>
          )}
          {trend && trendValue && (
            <div className="flex items-center space-x-1">
              {trend === "up" ? (
                <TrendingUp className="size-4 text-green-500" />
              ) : (
                <TrendingDown className="size-4 text-red-500" />
              )}
              <span className={cn(
                "text-xs font-medium",
                trend === "up" ? "text-green-500" : "text-red-500"
              )}>
                {trendValue}
              </span>
            </div>
          )}
        </div>
        {icon && (
          <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10">
            <div className="text-primary">{icon}</div>
          </div>
        )}
      </div>
    </Card>
  )
}